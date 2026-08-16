[CmdletBinding()]
param(
    [ValidateSet('quick', 'full', 'terminal')]
    [string] $Level = 'quick'
)

$ErrorActionPreference = 'Stop'
$loopRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$workspace = (Resolve-Path (Join-Path $loopRoot '..\..')).Path
$php = 'F:\Xampp 8\php\php.exe'
$composerPhar = 'C:\ProgramData\ComposerSetup\bin\composer.phar'
$baseline = Get-Content -LiteralPath (Join-Path $loopRoot 'baseline.json') -Raw | ConvertFrom-Json

function Invoke-Checked([string] $label, [scriptblock] $command) {
    Write-Host "`n== $label =="
    & $command
    if ($LASTEXITCODE -ne 0) {
        throw "$label failed with exit code $LASTEXITCODE."
    }
}

foreach ($test in $baseline.protected_existing_tests) {
    $path = Join-Path $workspace $test.path
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Protected baseline test is missing: $($test.path)"
    }
    $actual = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $test.sha256) {
        throw "Protected baseline test changed: $($test.path). Human approval is required to update the baseline."
    }
}

Push-Location $workspace
try {
    Invoke-Checked 'Laravel Pint' { & $php 'vendor\bin\pint' '--test' }

    $testOutput = & $php artisan test 2>&1 | Out-String
    Write-Host $testOutput
    if ($LASTEXITCODE -ne 0) {
        throw "Laravel tests failed with exit code $LASTEXITCODE."
    }
    $match = [regex]::Match($testOutput, 'Tests:\s+(\d+) passed')
    if (-not $match.Success -or [int] $match.Groups[1].Value -lt [int] $baseline.minimum_existing_test_count) {
        throw "Test-count invariant failed; expected at least $($baseline.minimum_existing_test_count) passing tests."
    }

    if ($Level -in @('full', 'terminal')) {
        if (-not (Test-Path -LiteralPath $composerPhar)) {
            throw "Composer PHAR not found: $composerPhar"
        }
        Invoke-Checked 'Composer QA' { & $php $composerPhar qa }
        Invoke-Checked 'Vite production build' { & npm.cmd run build }
        Invoke-Checked 'Route cache compatibility' { & $php artisan route:cache }
        Invoke-Checked 'Clear generated route cache' { & $php artisan route:clear }
    }

    if ($Level -eq 'terminal') {
        $state = Get-Content -LiteralPath (Join-Path $loopRoot 'state.json') -Raw | ConvertFrom-Json
        $incomplete = @($state.phases | Where-Object { $_.status -ne 'completed' })
        if ($incomplete.Count -gt 0) {
            throw "Terminal verification refused: $($incomplete.Count) phase(s) are not completed."
        }
        if (@($state.pending_human_decisions).Count -gt 0) {
            throw 'Terminal verification refused: pending human decisions remain.'
        }

        $routes = & $php artisan route:list --except-vendor 2>&1 | Out-String
        if ($LASTEXITCODE -ne 0) { throw 'Unable to inspect application routes.' }
        foreach ($prefix in @('admin/', 'staff/', 'homeowner/')) {
            if ($routes -notmatch [regex]::Escape($prefix)) {
                throw "Required route prefix is missing: $prefix"
            }
        }

        $evidencePath = Join-Path $loopRoot 'reports\acceptance-evidence.json'
        if (-not (Test-Path -LiteralPath $evidencePath)) {
            throw 'Terminal acceptance evidence is missing.'
        }
        $evidence = Get-Content -LiteralPath $evidencePath -Raw | ConvertFrom-Json
        if ($evidence.status -ne 'verified' -or @($evidence.scenarios).Count -lt 9) {
            throw 'Terminal acceptance evidence is incomplete.'
        }
    }
} finally {
    Pop-Location
}

Write-Host "`nVerification passed at level: $Level"
exit 0
