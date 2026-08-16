[CmdletBinding()]
param(
    [switch] $Start
)

$ErrorActionPreference = 'Stop'
$loopRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$workspace = (Resolve-Path (Join-Path $loopRoot '..\..')).Path
$configPath = Join-Path $loopRoot 'loop.config.json'
$statePath = Join-Path $loopRoot 'state.json'
$validatorPath = Join-Path $PSScriptRoot 'validate-loop.ps1'
$env:GIT_CONFIG_GLOBAL = Join-Path $loopRoot 'gitconfig'

& $validatorPath
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json

if (-not $Start) {
    Write-Host 'Status-only invocation. Supply -Start only after a human arms the loop.'
    exit 0
}

if (-not $config.enabled -or -not $state.armed -or $state.status -ne 'ready') {
    throw 'Loop is not armed. enabled=true, armed=true, and status=ready are all required.'
}

if (-not (Test-Path -LiteralPath (Join-Path $workspace '.git'))) {
    throw 'Loop start refused: Git recoverability is absent. Initialize Git or revise this gate with explicit human approval.'
}

function Get-Hashes([string[]] $relativePaths) {
    $hashes = @{}
    foreach ($relativePath in $relativePaths) {
        $path = Join-Path $workspace $relativePath
        if (-not (Test-Path -LiteralPath $path)) { throw "Protected file missing: $relativePath" }
        $hashes[$relativePath] = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash
    }
    return $hashes
}

function Assert-Hashes([hashtable] $expected) {
    foreach ($relativePath in $expected.Keys) {
        $actual = (Get-FileHash -LiteralPath (Join-Path $workspace $relativePath) -Algorithm SHA256).Hash
        if ($actual -ne $expected[$relativePath]) {
            throw "Protected loop control changed during an agent cycle: $relativePath"
        }
    }
}

function Get-WorkspaceFingerprint {
    $roots = @('app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'tests')
    $files = foreach ($root in $roots) {
        $path = Join-Path $workspace $root
        if (Test-Path -LiteralPath $path) { Get-ChildItem -LiteralPath $path -Recurse -File }
    }
    foreach ($name in @('composer.json', 'composer.lock', 'package.json', 'package-lock.json')) {
        $path = Join-Path $workspace $name
        if (Test-Path -LiteralPath $path) { $files += Get-Item -LiteralPath $path }
    }
    $material = $files | Sort-Object FullName | ForEach-Object {
        $relative = $_.FullName.Substring($workspace.Length + 1)
        "$relative|$((Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash)"
    }
    $bytes = [System.Text.Encoding]::UTF8.GetBytes(($material -join "`n"))
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try { return ([BitConverter]::ToString($sha.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant() } finally { $sha.Dispose() }
}

function Save-State($stateObject) {
    $stateObject.updated_at = [DateTimeOffset]::Now.ToString('o')
    $stateObject | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $statePath -Encoding utf8
}

$protectedHashes = Get-Hashes @($config.protected_paths)
$codex = Get-Command $config.codex.command -ErrorAction Stop
$promptPath = Join-Path $loopRoot 'prompts\controller.md'
$schemaPath = Join-Path $loopRoot 'schemas\agent-result.schema.json'
$runsRoot = Join-Path $loopRoot 'runs'
New-Item -ItemType Directory -Path $runsRoot -Force | Out-Null
$started = [DateTimeOffset]::Now

$state.status = 'running'
$state.started_at = $started.ToString('o')
$state.blocked_reason = $null
Save-State $state

while ($true) {
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    if ($state.status -notin @('ready', 'running')) { break }

    if ([int] $state.total_iterations -ge [int] $config.limits.max_total_iterations) {
        $state.status = 'budget_exhausted'
        $state.blocked_reason = 'Maximum total iteration count reached.'
        Save-State $state
        break
    }
    if ([int] $state.phase_iterations -ge [int] $config.limits.max_iterations_per_phase) {
        $state.status = 'blocked'
        $state.blocked_reason = 'Maximum iterations for the current phase reached.'
        Save-State $state
        break
    }
    if (([DateTimeOffset]::Now - $started).TotalMinutes -ge [double] $config.limits.max_wall_clock_minutes) {
        $state.status = 'budget_exhausted'
        $state.blocked_reason = 'Maximum wall-clock budget reached.'
        Save-State $state
        break
    }

    $iteration = [int] $state.total_iterations + 1
    $phaseBefore = $state.current_phase
    $runDirectory = Join-Path $runsRoot ('{0:D3}' -f $iteration)
    New-Item -ItemType Directory -Path $runDirectory -Force | Out-Null
    $stdoutPath = Join-Path $runDirectory 'events.jsonl'
    $stderrPath = Join-Path $runDirectory 'stderr.log'
    $lastMessagePath = Join-Path $runDirectory 'result.json'
    $fingerprintBefore = Get-WorkspaceFingerprint

    $arguments = @(
        'exec',
        '--approve-for-me',
        '--cd', ('"' + $workspace + '"'),
        '--output-schema', ('"' + $schemaPath + '"'),
        '--output-last-message', ('"' + $lastMessagePath + '"'),
        '--ephemeral',
        '-'
    )

    $process = Start-Process -FilePath $codex.Source -ArgumentList $arguments -RedirectStandardInput $promptPath -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath -PassThru -WindowStyle Hidden
    $timeoutSeconds = [int] $config.limits.per_iteration_timeout_minutes * 60
    try {
        Wait-Process -Id $process.Id -Timeout $timeoutSeconds -ErrorAction Stop
    } catch {
        Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
        $state.status = 'blocked'
        $state.blocked_reason = "Agent iteration $iteration exceeded $timeoutSeconds seconds."
        Save-State $state
        break
    }

    Assert-Hashes $protectedHashes
    if ($process.ExitCode -ne 0 -or -not (Test-Path -LiteralPath $lastMessagePath)) {
        $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
        $state.status = 'blocked'
        $state.blocked_reason = "Codex iteration $iteration failed. See $stderrPath"
        Save-State $state
        break
    }

    $result = Get-Content -LiteralPath $lastMessagePath -Raw | ConvertFrom-Json
    $fingerprintAfter = Get-WorkspaceFingerprint
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    $state.total_iterations = [int] $state.total_iterations + 1
    if ($state.current_phase -ne $phaseBefore) {
        $state.phase_iterations = 0
    } else {
        $state.phase_iterations = [int] $state.phase_iterations + 1
    }

    if ($fingerprintAfter -eq $fingerprintBefore) {
        $state.consecutive_no_progress = [int] $state.consecutive_no_progress + 1
    } else {
        $state.consecutive_no_progress = 0
    }
    $state.last_fingerprint = $fingerprintAfter

    if ([int] $state.consecutive_no_progress -ge [int] $config.limits.max_consecutive_no_progress -and $result.outcome -notin @('awaiting_human', 'complete', 'no_op')) {
        $state.status = 'blocked'
        $state.blocked_reason = 'No-progress fingerprint repeated for the configured limit.'
    }
    Save-State $state
}

$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
Write-Host "Loop stopped with state: $($state.status)"
if ($state.blocked_reason) { Write-Host "Reason: $($state.blocked_reason)" }
exit 0
