if (Test-Path "pids.txt") {
    $pids = Get-Content "pids.txt"
    foreach ($apid in $pids) {
        Stop-Process -Id $apid -Force -ErrorAction SilentlyContinue
        Write-Host "Stop process: $apid"
    }
    Remove-Item "pids.txt"
}