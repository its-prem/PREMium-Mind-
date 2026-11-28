# Makes the app icons of the website (public/icons/*.png) from public/pm-logo.png.
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts/make-pwa-icons.ps1
# icon-192 / icon-512 : the logo on a dark rounded square (a normal app icon)
# icon-maskable-512   : the logo smaller, on a full dark square (Android may cut the corners into a circle)
Add-Type -AssemblyName System.Drawing
$root = Split-Path -Parent $PSScriptRoot
$src  = Join-Path $root 'public\pm-logo.png'
$out  = Join-Path $root 'public\icons'
New-Item -ItemType Directory -Force $out | Out-Null
$logo = [System.Drawing.Image]::FromFile($src)
Write-Host "logo: $($logo.Width) x $($logo.Height)"

function New-Icon([int]$size, [double]$logoScale, [bool]$rounded, [string]$name) {
  $bmp = New-Object System.Drawing.Bitmap $size, $size
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.SmoothingMode = 'AntiAlias'; $g.InterpolationMode = 'HighQualityBicubic'; $g.PixelOffsetMode = 'HighQuality'
  $g.Clear([System.Drawing.Color]::Transparent)
  $bg = New-Object System.Drawing.Drawing2D.LinearGradientBrush ([System.Drawing.Point]::new(0, 0)), ([System.Drawing.Point]::new($size, $size)), ([System.Drawing.Color]::FromArgb(255, 28, 10, 14)), ([System.Drawing.Color]::FromArgb(255, 104, 9, 21))
  if ($rounded) {
    $r = [int]($size * 0.22); $d = $r * 2
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $path.AddArc(0, 0, $d, $d, 180, 90); $path.AddArc($size - $d, 0, $d, $d, 270, 90)
    $path.AddArc($size - $d, $size - $d, $d, $d, 0, 90); $path.AddArc(0, $size - $d, $d, $d, 90, 90); $path.CloseFigure()
    $g.FillPath($bg, $path)
  } else {
    $g.FillRectangle($bg, 0, 0, $size, $size)
  }
  $box = $size * $logoScale
  $ratio = [Math]::Min($box / $logo.Width, $box / $logo.Height)
  $w = $logo.Width * $ratio; $h = $logo.Height * $ratio
  $g.DrawImage($logo, ($size - $w) / 2, ($size - $h) / 2, $w, $h)
  $bmp.Save((Join-Path $out $name), [System.Drawing.Imaging.ImageFormat]::Png)
  $g.Dispose(); $bmp.Dispose()
  Write-Host "written $name"
}

New-Icon 192 0.70 $true  'icon-192.png'
New-Icon 512 0.70 $true  'icon-512.png'
New-Icon 512 0.52 $false 'icon-maskable-512.png'
New-Icon 180 0.70 $false 'apple-touch-icon.png'
$logo.Dispose()
