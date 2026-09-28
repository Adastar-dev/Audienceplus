<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Décision {{ $reference }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; }
    .entete { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 20px; }
    .entete h1 { font-size: 16pt; margin: 0; color: #1e3a5f; }
    .entete p { margin: 2px 0; font-size: 10pt; }
    table.infos { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.infos td { padding: 5px 8px; border: 1px solid #cbd5e1; vertical-align: top; }
    table.infos td.libelle { width: 32%; background: #f1f5f9; font-weight: bold; }
    h2 { font-size: 12pt; color: #1e3a5f; margin: 18px 0 6px; }
    .texte { white-space: pre-wrap; }
    .pied { margin-top: 30px; width: 100%; }
    .pied td { vertical-align: top; }
    .qr img { width: 120px; height: 120px; }
    .petit { font-size: 8.5pt; color: #475569; }
</style>
</head>
<body>
    <div class="entete">
        <p>République du Sénégal</p>
        <p>{{ $dossier->tribunal->nom ?? 'Tribunal' }}{{ $dossier->tribunal?->ville ? ' - '.$dossier->tribunal->ville : '' }}</p>
        <h1>{{ $libelle }}</h1>
        <p>Référence : {{ $reference }}</p>
    </div>

    <table class="infos">
        <tr><td class="libelle">Dossier</td><td>{{ $dossier->numero }}</td></tr>
        <tr><td class="libelle">Type de procédure</td><td>{{ str_replace('_', ' ', ucfirst(strtolower($dossier->type))) }}</td></tr>
        <tr><td class="libelle">Parties</td><td>{{ $dossier->parties }}</td></tr>
        <tr><td class="libelle">Audience du</td><td>{{ $audience->date_heure->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="libelle">Juge</td><td>{{ $audience->juge->nom ?? '-' }}</td></tr>
        <tr><td class="libelle">Décision</td><td>{{ $libelle }}</td></tr>
    </table>

    <h2>Motifs et dispositif</h2>
    <div class="texte">{{ $audience->motif_decision ?: 'Aucun motif saisi.' }}</div>

    @if ($pvValide)
        <h2>Procès-verbal d'audience (validé)</h2>
        <div class="texte">{{ $audience->procesVerbal->contenu }}</div>
    @endif

    <table class="pied">
        <tr>
            <td class="qr"><img src="{{ $qr }}" alt="QR code"></td>
            <td class="petit">
                Document généré par Audience+ le {{ now()->format('d/m/Y à H:i') }}.<br>
                Empreinte d'authenticité : {{ $empreinte }}<br>
                Le QR code reprend la référence de la décision, le numéro du dossier et cette empreinte :
                le greffe peut vérifier qu'ils correspondent à la décision enregistrée.
            </td>
        </tr>
    </table>
</body>
</html>
