Option Explicit
' Native COM alternative to PowerShell's Office method binder. Never edits the source.
Dim fs, source, destination, word, document
Set fs = CreateObject("Scripting.FileSystemObject")
If WScript.Arguments.Count <> 2 Then
    WScript.Echo "Usage: cscript //nologo export-original-document.vbs preserved.docx separate.pdf"
    WScript.Quit 2
End If
source = fs.GetAbsolutePathName(WScript.Arguments(0))
destination = fs.GetAbsolutePathName(WScript.Arguments(1))
If Not fs.FileExists(source) Or fs.FileExists(destination) Then
    WScript.Echo "Missing source or existing output; refusing conversion."
    WScript.Quit 2
End If
On Error Resume Next
Set word = CreateObject("Word.Application")
CheckError "Create isolated Word instance"
word.Visible = False
word.DisplayAlerts = 0
word.AutomationSecurity = 3
Set document = word.Documents.Open(source, False, True, False)
CheckError "Open preservation copy read-only"
WScript.Echo "Read-only source opened; exporting with native Word COM."
document.ExportAsFixedFormat destination, 17, False, 0, 0, 1, 1, 0, False, False, 0, False, True, False
CheckError "Export PDF"
document.Close 0
word.Quit 0
Set document = Nothing
Set word = Nothing
WScript.Echo "Word PDF export complete."

Sub CheckError(stage)
    If Err.Number <> 0 Then
        WScript.Echo stage & ": " & Hex(Err.Number) & " " & Err.Description
        Err.Clear
        If IsObject(document) Then document.Close 0
        If IsObject(word) Then word.Quit 0
        WScript.Quit 1
    End If
End Sub
