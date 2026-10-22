param(
    [Parameter(Mandatory = $true)][string]$Source,
    [Parameter(Mandatory = $true)][string]$Destination
)
$ErrorActionPreference = 'Stop'
$sourceFile = (Resolve-Path -LiteralPath $Source).Path
$destinationFile = [System.IO.Path]::GetFullPath($Destination)
if (Test-Path -LiteralPath $destinationFile) { throw 'Refusing to overwrite an existing reference PDF.' }
$sourceHash = (Get-FileHash -LiteralPath $sourceFile -Algorithm SHA256).Hash
$wordInstance = $null
$wordDocument = $null
try {
    $wordInstance = New-Object -ComObject Word.Application
    $wordInstance.Visible = $false
    $wordInstance.DisplayAlerts = 0
    $wordInstance.AutomationSecurity = 3
    $wordDocument = $wordInstance.Documents.Open($sourceFile, $false, $true, $false)
    $wordDocument.ExportAsFixedFormat($destinationFile, 17)
    if ((Get-FileHash -LiteralPath $sourceFile -Algorithm SHA256).Hash -ne $sourceHash) { throw 'Source document changed unexpectedly.' }
    Write-Output "Source SHA256: $sourceHash"
    Write-Output "PDF SHA256: $((Get-FileHash -LiteralPath $destinationFile -Algorithm SHA256).Hash)"
} finally {
    $saveChanges = 0
    if ($null -ne $wordDocument) { $wordDocument.Close([ref]$saveChanges); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($wordDocument) }
    if ($null -ne $wordInstance) { $wordInstance.Quit([ref]$saveChanges); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($wordInstance) }
}
