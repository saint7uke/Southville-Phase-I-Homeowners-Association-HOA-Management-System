Option Explicit
' Native Word export; separate output, read-only source, no shared user Word instance.
Dim fs, source, destination, method, word, document, wmi, process, before, ownedPid, newCount, previousLinkUpdates
Set fs = CreateObject("Scripting.FileSystemObject")
If WScript.Arguments.Count < 2 Then
    WScript.Echo "Usage: cscript //nologo //T:45 export-cohabitation-reference.vbs source.docx separate.pdf [SaveAs]"
    WScript.Quit 2
End If
source = fs.GetAbsolutePathName(WScript.Arguments(0))
destination = fs.GetAbsolutePathName(WScript.Arguments(1))
method = "Export"
If WScript.Arguments.Count = 3 Then method = WScript.Arguments(2)
If Not fs.FileExists(source) Or fs.FileExists(destination) Or source = destination Then
    WScript.Echo "Missing source or existing destination; refusing overwrite."
    WScript.Quit 2
End If
Set wmi = GetObject("winmgmts:\\.\root\cimv2")
Set before = CreateObject("Scripting.Dictionary")
For Each process In wmi.ExecQuery("SELECT ProcessId FROM Win32_Process WHERE Name='WINWORD.EXE'")
    before.Add CStr(process.ProcessId), True
Next
On Error Resume Next
Set word = CreateObject("Word.Application")
If Err.Number <> 0 Then
    WScript.Echo "Create Word: " & Hex(Err.Number) & " " & Err.Description
    WScript.Quit 1
End If
newCount = 0
For Each process In wmi.ExecQuery("SELECT ProcessId FROM Win32_Process WHERE Name='WINWORD.EXE'")
    If Not before.Exists(CStr(process.ProcessId)) Then
        ownedPid = process.ProcessId
        newCount = newCount + 1
    End If
Next
If newCount <> 1 Then
    WScript.Echo "Word did not create one isolated process. User documents will not be touched."
    WScript.Quit 1
End If
WScript.Echo "Owned Word process: " & ownedPid
word.Visible = False
word.DisplayAlerts = 0
word.AutomationSecurity = 3
previousLinkUpdates = word.Options.UpdateLinksAtOpen
word.Options.UpdateLinksAtOpen = False
Set document = word.Documents.Open(source, False, True, False)
CheckError "Open source read-only"
WScript.Echo "Read-only source opened, macros and automatic link updates disabled."
If method = "SaveAs" Then
    document.SaveAs2 destination, 17
Else
    document.ExportAsFixedFormat destination, 17
End If
CheckError "Word PDF export"
document.Close 0
word.Options.UpdateLinksAtOpen = previousLinkUpdates
word.Quit 0
WScript.Echo "Native Word PDF export complete."

Sub CheckError(stage)
    If Err.Number <> 0 Then
        WScript.Echo stage & ": " & Hex(Err.Number) & " " & Err.Description
        Err.Clear
        If IsObject(document) Then document.Close 0
        word.Options.UpdateLinksAtOpen = previousLinkUpdates
        word.Quit 0
        WScript.Quit 1
    End If
End Sub
