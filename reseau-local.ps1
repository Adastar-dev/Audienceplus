# Prépare une démonstration sur plusieurs appareils du même Wi-Fi (téléphone,
# second PC) : détecte l'adresse IP du PC puis
#  - l'annonce à Jitsi (JITSI_ADVERTISE_IP, fichier .env à la racine) pour que
#    l'audio et la vidéo passent entre les appareils ;
#  - l'utilise dans les liens des e-mails et des QR codes (FRONTEND_URL du backend).
# À relancer à chaque changement de réseau Wi-Fi.
#   Utilisation : clic droit > Exécuter avec PowerShell, ou  .\reseau-local.ps1
#   Pour revenir au seul PC :                                .\reseau-local.ps1 -Local

param([switch]$Local)

$racine = $PSScriptRoot
$ip = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*', 'Ethernet*' -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -match '^(192\.168|10\.|172\.(1[6-9]|2\d|3[01]))' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Select-Object -First 1).IPAddress

if ($Local -or -not $ip) {
    if (-not $Local) { Write-Host "Aucune adresse Wi-Fi trouvée : configuration locale." }
    $annonce = '127.0.0.1'; $frontend = 'http://localhost:5173'
} else {
    $annonce = "127.0.0.1,$ip"; $frontend = "http://${ip}:5173"
}

# Écriture en UTF-8 sans BOM : un BOM empêcherait Laravel de lire la première ligne du .env.
function Ecrire($chemin, $contenu) { [IO.File]::WriteAllLines($chemin, [string[]]$contenu, (New-Object Text.UTF8Encoding $false)) }

# .env racine (lu par docker compose) : adresse annoncée par le Videobridge.
$envRacine = Join-Path $racine '.env'
$lignes = @(if (Test-Path $envRacine) { Get-Content $envRacine | Where-Object { $_ -notmatch '^JITSI_ADVERTISE_IP=' } })
Ecrire $envRacine ($lignes + "JITSI_ADVERTISE_IP=$annonce")

# .env du backend : adresse du site utilisée dans les e-mails et les QR codes.
$envBackend = Join-Path $racine 'audiences-judiciaires-backend\.env'
Ecrire $envBackend ((Get-Content $envBackend -Encoding utf8) -replace '^FRONTEND_URL=.*', "FRONTEND_URL=$frontend")

Set-Location $racine
docker compose up -d jitsi-jvb | Out-Null
docker compose exec -T -u www-data -e HOME=/tmp app php artisan config:clear | Out-Null

Write-Host ""
Write-Host "Jitsi annonce : $annonce"
Write-Host "Site          : $frontend"
if (-not $Local -and $ip) {
    Write-Host ""
    Write-Host "Sur chaque appareil (même Wi-Fi) :"
    Write-Host "  1. ouvrir https://${ip}:8443 et accepter le certificat ;"
    Write-Host "  2. puis ouvrir $frontend"
}
