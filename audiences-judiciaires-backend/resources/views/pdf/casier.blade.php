<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Extrait de casier judiciaire {{ $casier->qr_code }}</title>
<style>
    @page { margin: 22mm 18mm 24mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.45; }
    .bleu { color: #1e3a5f; }
    table.entete { width: 100%; border-bottom: 2px solid #1e3a5f; padding-bottom: 8px; }
    table.entete td { vertical-align: top; font-size: 9pt; }
    .etat { font-weight: bold; letter-spacing: 0.5px; }
    .devise { font-style: italic; font-size: 8pt; color: #475569; }
    .titre { text-align: center; margin: 22px 0 6px; }
    .titre h1 { font-size: 16pt; margin: 0; color: #1e3a5f; letter-spacing: 1px; text-transform: uppercase; }
    .titre p { margin: 3px 0 0; font-size: 9.5pt; color: #475569; }
    h2 { font-size: 10.5pt; color: #1e3a5f; text-transform: uppercase; letter-spacing: 0.5px;
         border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 20px 0 8px; }
    table.infos { width: 100%; border-collapse: collapse; }
    table.infos td { padding: 5px 8px; border: 1px solid #e2e8f0; }
    table.infos td.libelle { width: 32%; background: #f1f5f9; font-weight: bold; color: #334155; }
    .resultat { border: 2px solid #1e3a5f; text-align: center; padding: 14px; margin-top: 6px; }
    .resultat .grand { font-size: 20pt; font-weight: bold; color: #1e3a5f; letter-spacing: 3px; }
    .mention { font-size: 8pt; color: #475569; }
    table.verif { width: 100%; margin-top: 24px; border: 1px solid #cbd5e1; page-break-inside: avoid; line-height: 1.3; }
    table.verif td { vertical-align: middle; padding: 8px; }
    .qr img { width: 105px; height: 105px; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; }
    .specimen { position: fixed; top: 95mm; left: 5mm; width: 170mm; text-align: center;
                font-size: 60pt; font-weight: bold; color: #e2e8f0; transform: rotate(-30deg); z-index: -1; }
    .pied { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #64748b;
            border-top: 1px solid #e2e8f0; padding-top: 3px; }
</style>
</head>
<body>
    <div class="specimen">SPÉCIMEN</div>
    <div class="pied">
        Audience+ · prototype académique · extrait à résultat simulé, sans valeur juridique · {{ $casier->qr_code }}
    </div>

    <table class="entete">
        <tr>
            <td style="width:50%">
                <div class="etat bleu">RÉPUBLIQUE DU SÉNÉGAL</div>
                <div class="devise">Un Peuple – Un But – Une Foi</div>
            </td>
            <td style="width:50%; text-align:right">
                <div class="etat bleu">Service du casier judiciaire</div>
                <div class="mention">Module de démonstration Audience+</div>
            </td>
        </tr>
    </table>

    <div class="titre">
        <h1>Extrait de casier judiciaire</h1>
        <p>Bulletin n° 3 · référence {{ $casier->qr_code }}</p>
    </div>

    <h2>Titulaire</h2>
    <table class="infos">
        <tr><td class="libelle">Nom et prénom(s)</td><td>{{ $titulaire->nom }}</td></tr>
        <tr><td class="libelle">N° de carte d'identité</td><td>{{ $titulaire->cni ?? '-' }}</td></tr>
        <tr><td class="libelle">Identité vérifiée</td><td>{{ $titulaire->identite_verifiee ? 'Oui, par l\'administration de la plateforme' : 'Non' }}</td></tr>
    </table>

    <h2>Condamnations inscrites</h2>
    <div class="resultat">
        @if ($casier->resultat === 'VIERGE')
            <div class="grand">NÉANT</div>
            <div class="mention">Aucune condamnation n'est inscrite au bulletin n° 3.</div>
        @else
            <div class="grand">MENTIONS INSCRITES</div>
        @endif
    </div>

    <h2>Délivrance</h2>
    <table class="infos">
        <tr><td class="libelle">Demandé le</td><td>{{ $casier->date_demandee->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="libelle">Délivré le</td><td>{{ now()->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="libelle">Référence unique</td><td>{{ $casier->qr_code }}</td></tr>
    </table>

    <table class="verif">
        <tr>
            <td class="qr" style="width:115px"><img src="{{ $casier->qr_image }}" alt="QR code de vérification"></td>
            <td>
                <strong class="bleu">Vérifier cet extrait</strong><br>
                <span class="mention">Scannez le QR code, ou saisissez la référence <strong>{{ $casier->qr_code }}</strong> sur la page de vérification d'Audience+ :</span><br>
                <span class="mono">{{ $casier->url_verification }}</span><br>
                <span class="mention">Le résultat de ce module est simulé : la délivrance officielle d'un extrait relève de la plateforme e-Sénégal.</span>
            </td>
        </tr>
    </table>
</body>
</html>
