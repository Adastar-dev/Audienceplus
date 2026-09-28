<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Extrait de casier judiciaire {{ $casier->qr_code }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; }
    .entete { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 20px; }
    .entete h1 { font-size: 16pt; margin: 0; color: #1e3a5f; }
    .entete p { margin: 2px 0; font-size: 10pt; }
    table.infos { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.infos td { padding: 5px 8px; border: 1px solid #cbd5e1; }
    table.infos td.libelle { width: 32%; background: #f1f5f9; font-weight: bold; }
    .pied { margin-top: 30px; width: 100%; }
    .pied td { vertical-align: top; }
    .qr img { width: 120px; height: 120px; }
    .petit { font-size: 8.5pt; color: #475569; }
</style>
</head>
<body>
    <div class="entete">
        <p>République du Sénégal</p>
        <h1>Extrait de casier judiciaire</h1>
        <p>Référence : {{ $casier->qr_code }}</p>
    </div>

    <table class="infos">
        <tr><td class="libelle">Titulaire</td><td>{{ $titulaire->nom }}</td></tr>
        <tr><td class="libelle">N° de CNI</td><td>{{ $titulaire->cni ?? '-' }}</td></tr>
        <tr><td class="libelle">Date de la demande</td><td>{{ $casier->date_demandee->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="libelle">Résultat</td><td>{{ $casier->resultat === 'VIERGE' ? 'Néant (casier vierge)' : 'Casier non vierge' }}</td></tr>
    </table>

    <table class="pied">
        <tr>
            <td class="qr"><img src="{{ $casier->qr_image }}" alt="QR code"></td>
            <td class="petit">
                Document généré par Audience+ le {{ now()->format('d/m/Y à H:i') }}.<br>
                Le QR code contient la référence unique de cet extrait ; le greffe peut
                la rechercher pour s'assurer que l'extrait a bien été délivré.
            </td>
        </tr>
    </table>
</body>
</html>
