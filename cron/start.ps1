$processes = @(
    @{Name="bot"; Command="php"; Args="bot.php"},
    @{Name="DBServer"; Command="php"; Args="main_cycle.php"},
    @{Name="App"; Command="php"; Args="downloader.php"}
)

$pids = @()
foreach ($proc in $processes) {
    $p = Start-Process -FilePath $proc.Command -ArgumentList $proc.Args -PassThru -WindowStyle Hidden
    $pids += $p.Id
    Write-Host "Run $($proc.Name) PID: $($p.Id)"
}

$pids | Out-File "pids.txt"
Write-Host "PID saved in pids.txt"