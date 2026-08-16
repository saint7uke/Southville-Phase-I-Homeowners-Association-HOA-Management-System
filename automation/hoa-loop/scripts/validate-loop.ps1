[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$loopRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$workspace = (Resolve-Path (Join-Path $loopRoot '..\..')).Path
$errors = [System.Collections.Generic.List[string]]::new()
$warnings = [System.Collections.Generic.List[string]]::new()

function Read-JsonFile([string] $path) {
    try {
        return Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
    } catch {
        $errors.Add("Invalid JSON: $path - $($_.Exception.Message)")
        return $null
    }
}

$config = Read-JsonFile (Join-Path $loopRoot 'loop.config.json')
$state = Read-JsonFile (Join-Path $loopRoot 'state.json')
$null = Read-JsonFile (Join-Path $loopRoot 'baseline.json')
$null = Read-JsonFile (Join-Path $loopRoot 'acceptance.json')
$null = Read-JsonFile (Join-Path $loopRoot 'schemas\agent-result.schema.json')

if ($config -and $state) {
    $phaseIds = @($state.phases | ForEach-Object { $_.id })
    if ($state.current_phase -notin $phaseIds) {
        $errors.Add("Current phase '$($state.current_phase)' is not present in state.phases.")
    }

    foreach ($relativePath in $config.protected_paths) {
        if (-not (Test-Path -LiteralPath (Join-Path $workspace $relativePath))) {
            $errors.Add("Missing protected file: $relativePath")
        }
    }

    foreach ($sourcePath in @($config.source.snapshot, $config.source.normalized_spec)) {
        if (-not (Test-Path -LiteralPath (Join-Path $workspace $sourcePath))) {
            $errors.Add("Missing source snapshot: $sourcePath")
        }
    }
}

foreach ($script in @('run-loop.ps1', 'validate-loop.ps1', '..\verifiers\verify.ps1')) {
    $scriptPath = Join-Path $PSScriptRoot $script
    if (-not (Test-Path -LiteralPath $scriptPath)) {
        $errors.Add("Missing PowerShell script: $scriptPath")
        continue
    }
    $tokens = $null
    $parseErrors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile($scriptPath, [ref] $tokens, [ref] $parseErrors)
    foreach ($parseError in $parseErrors) {
        $errors.Add("PowerShell syntax error in $scriptPath at line $($parseError.Extent.StartLineNumber): $($parseError.Message)")
    }
}

if (-not (Get-Command codex -ErrorAction SilentlyContinue)) {
    $errors.Add('Codex CLI is not available on PATH.')
}

if (-not (Test-Path -LiteralPath (Join-Path $workspace '.git'))) {
    $warnings.Add('Git metadata is absent. The runner will refuse implementation until recoverability is approved and configured.')
}

Write-Host "HOA loop setup: $loopRoot"
Write-Host "Enabled: $($config.enabled) | State: $($state.status) | Armed: $($state.armed)"
foreach ($warning in $warnings) { Write-Warning $warning }

if ($errors.Count -gt 0) {
    foreach ($validationError in $errors) { Write-Error $validationError }
    exit 1
}

Write-Host 'Validation passed. No loop or agent was started.'
exit 0
