param(
    [string]$Source = 'C:\Users\bargo\Downloads\resibo butaw.docx',
    [string]$DestinationDirectory = (Join-Path $PSScriptRoot '../resources/certificates/resibo-butaw-2026'),
    [ValidateSet('FixedPdf', 'SaveAsPdf', 'FixedXps')][string]$Method = 'FixedPdf',
    [switch]$ReusePreservedCopy
)
$ErrorActionPreference = 'Stop'
$sourceFile = (Resolve-Path -LiteralPath $Source).Path
$targetDirectory = [IO.Path]::GetFullPath($DestinationDirectory)
if ((Test-Path -LiteralPath $targetDirectory) -and -not $ReusePreservedCopy) { throw 'Refusing to overwrite an existing original-document package. Use -ReusePreservedCopy only after checking its checksum.' }
function Read-SourceBytes {
    $stream = [IO.File]::Open($sourceFile, [IO.FileMode]::Open, [IO.FileAccess]::Read, ([IO.FileShare]::ReadWrite -bor [IO.FileShare]::Delete))
    try {
        $buffer = New-Object IO.MemoryStream
        $stream.CopyTo($buffer)
        return ,$buffer.ToArray()
    } finally { $stream.Dispose(); if ($buffer) { $buffer.Dispose() } }
}
function Get-BytesHash([byte[]]$bytes) {
    $sha = [Security.Cryptography.SHA256]::Create()
    try { return [BitConverter]::ToString($sha.ComputeHash($bytes)).Replace('-', '').ToLowerInvariant() } finally { $sha.Dispose() }
}
$sourceBytes = Read-SourceBytes
$sourceHash = Get-BytesHash $sourceBytes
if ($sourceHash -ne (Get-BytesHash (Read-SourceBytes))) { throw 'The original is changing. Save it and retry.' }
if (-not (Test-Path -LiteralPath $targetDirectory)) { [void](New-Item -ItemType Directory -Path $targetDirectory) }
$copy = Join-Path $targetDirectory 'original.docx'
# An exact binary preservation copy, never an edited or reconstructed document.
if (Test-Path -LiteralPath $copy) {
    if ((Get-FileHash -LiteralPath $copy -Algorithm SHA256).Hash.ToLowerInvariant() -ne $sourceHash) { throw 'The preservation copy does not match the saved original.' }
} else { [IO.File]::WriteAllBytes($copy, $sourceBytes) }
$wordInstance = $null
$wordDocument = $null
try {
    $wordInstance = New-Object -ComObject Word.Application
    $wordInstance.Visible = $false
    $wordInstance.DisplayAlerts = 0
    $wordInstance.AutomationSecurity = 3
    Write-Output "Starting $Method with a hidden Word instance; macros disabled."
    $wordDocument = $wordInstance.Documents.Open($copy, $false, $true, $false)
    Write-Output 'Preservation copy opened read-only.'
    $pdf = Join-Path $targetDirectory $(if ($Method -eq 'FixedXps') { 'reference.xps' } else { 'reference.pdf' })
    if (Test-Path -LiteralPath $pdf) { throw 'Refusing to overwrite an existing exported reference.' }
    if ($Method -eq 'SaveAsPdf') { $wordDocument.SaveAs2($pdf, 17) }
    elseif ($Method -eq 'FixedXps') { $wordDocument.ExportAsFixedFormat($pdf, 18, $false, 0, 0, 1, 1, 0, $false, $false, 0, $false, $true, $false) }
    else { $wordDocument.ExportAsFixedFormat($pdf, 17, $false, 0, 0, 1, 1, 0, $false, $false, 0, $false, $true, $false) }
    if ($sourceHash -ne (Get-BytesHash (Read-SourceBytes))) { throw 'Source changed during export. This package must not be approved.' }
    Write-Output "Source SHA256: $sourceHash"
    Write-Output "PDF SHA256: $((Get-FileHash -LiteralPath $pdf -Algorithm SHA256).Hash.ToLowerInvariant())"
} finally {
    $saveChanges = 0
    if ($null -ne $wordDocument) { $wordDocument.Close([ref]$saveChanges); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($wordDocument) }
    if ($null -ne $wordInstance) { $wordInstance.Quit([ref]$saveChanges); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($wordInstance) }
}
