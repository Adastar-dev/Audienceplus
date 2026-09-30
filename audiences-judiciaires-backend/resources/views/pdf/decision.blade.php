<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $libelle }} {{ $reference }}</title>
<style>
    @page { margin: 22mm 18mm 24mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.45; }
    .bleu { color: #1e3a5f; }
    table.entete { width: 100%; border-bottom: 2px solid #1e3a5f; padding-bottom: 8px; }
    table.entete td { vertical-align: top; font-size: 9pt; }
    .etat { font-weight: bold; letter-spacing: 0.5px; }
    .devise { font-style: italic; font-size: 8pt; color: #475569; }
    .titre { text-align: center; margin: 18px 0 4px; }
    .titre h1 { font-size: 17pt; margin: 0; color: #1e3a5f; letter-spacing: 1px; text-transform: uppercase; }
    .titre p { margin: 3px 0 0; font-size: 9pt; color: #475569; }
    h2 { font-size: 10.5pt; color: #1e3a5f; text-transform: uppercase; letter-spacing: 0.5px;
         border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 14px 0 6px; }
    table.infos { width: 100%; border-collapse: collapse; }
    table.infos td { padding: 4px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
    table.infos td.libelle { width: 30%; background: #f1f5f9; font-weight: bold; color: #334155; }
    .texte { white-space: pre-wrap; text-align: justify; }
    .dispositif { border: 1px solid #1e3a5f; background: #f8fafc; padding: 10px 12px; margin-top: 6px; }
    .dispositif .decision { font-weight: bold; font-size: 11pt; color: #1e3a5f; margin-bottom: 6px; }
    table.signatures { width: 100%; margin-top: 14px; }
    table.signatures td { width: 50%; text-align: center; vertical-align: top; font-size: 9pt; }
    .mention { font-size: 8pt; color: #475569; }
    table.verif { width: 100%; margin-top: 14px; border: 1px solid #cbd5e1; page-break-inside: avoid; line-height: 1.3; }
    table.verif td { vertical-align: middle; padding: 6px 8px; }
    .qr img { width: 95px; height: 95px; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; word-wrap: break-word; }
    .pied { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #64748b;
            border-top: 1px solid #e2e8f0; padding-top: 3px; }
    .pied .page:after { content: counter(page); }
</style>
</head>
<body>
    <div class="pied">
        <table width="100%"><tr>
            <td>Audience+ · prototype académique · document sans valeur juridique · {{ $reference }}</td>
            <td style="text-align:right">Page <span class="page"></span></td>
        </tr></table>
    </div>

    <table class="entete">
        <tr>
            <td style="width:50%">
                <div class="etat bleu">RÉPUBLIQUE DU SÉNÉGAL</div>
                <div class="devise">Un Peuple – Un But – Une Foi</div>
            </td>
            <td style="width:50%; text-align:right">
                <div class="etat bleu">{{ $dossier->tribunal->nom ?? 'Tribunal' }}</div>
                <div>{{ $dossier->tribunal->ville ?? '' }}</div>
            </td>
        </tr>
    </table>

    <div class="titre">
        <h1>{{ $libelle }}</h1>
        <p>Référence {{ $reference }} · audience du {{ $audience->date_heure->locale('fr')->translatedFormat('d F Y') }} à {{ $audience->date_heure->format('H\hi') }}</p>
    </div>

    <h2>I. Affaire</h2>
    <table class="infos">
        <tr><td class="libelle">Dossier n°</td><td>{{ $dossier->numero }}</td></tr>
        <tr><td class="libelle">Nature de la procédure</td><td>{{ ucfirst(strtolower(str_replace('_', ' ', $dossier->type))) }}</td></tr>
        <tr><td class="libelle">Modalité de l'audience</td>
            <td>{{ $hybride ? 'Audience au tribunal, avec comparution à distance autorisée par le juge' : 'Audience au tribunal' }}</td></tr>
    </table>

    <h2>II. Parties</h2>
    <table class="infos">
        <tr><td class="libelle">{{ $defendeur ? 'Demandeur' : 'Requérant' }}</td>
            <td>{{ $demandeur }}@if ($avocatDemandeur)<br><span class="mention">représenté(e) par Me {{ $avocatDemandeur }}</span>@endif</td></tr>
        @if ($defendeur)
            <tr><td class="libelle">Défendeur</td>
                <td>{{ $defendeur }}@if ($avocatDefendeur)<br><span class="mention">représenté(e) par Me {{ $avocatDefendeur }}</span>@endif</td></tr>
        @endif
    </table>

    <h2>III. Composition et comparution</h2>
    <table class="infos">
        <tr><td class="libelle">Juge</td><td>{{ $audience->juge->nom ?? '-' }}</td></tr>
        @if ($procureur)
            <tr><td class="libelle">Ministère public</td><td>{{ $procureur }}</td></tr>
        @endif
        <tr><td class="libelle">Présents (émargement)</td>
            <td>
                @forelse ($presents as $p)
                    {{ $p['nom'] }} <span class="mention">({{ $p['qualite'] }}, {{ strtolower($p['modalite']) }})</span><br>
                @empty
                    <span class="mention">Aucune présence enregistrée sur la plateforme.</span>
                @endforelse
            </td></tr>
    </table>

    @if ($avisParquet)
        <h2>IV. Avis du ministère public</h2>
        <div class="texte">{{ $avisParquet }}</div>
    @endif

    <h2>{{ $avisParquet ? 'V' : 'IV' }}. Dispositif</h2>
    <div class="dispositif">
        <div class="decision">
            @if ($audience->type_decision === 'JUGEMENT')
                Le tribunal statue et rend le jugement suivant :
            @elseif ($audience->type_decision === 'RENVOI')
                L'affaire est renvoyée à une audience ultérieure.
            @else
                L'affaire est mise en délibéré.
            @endif
        </div>
        <div class="texte">{{ $audience->motif_decision ?: 'Aucun motif saisi.' }}</div>
    </div>

    <table class="signatures">
        <tr>
            <td>Le Juge<br><strong>{{ $audience->juge->nom ?? '' }}</strong></td>
            <td>Le Greffier<br>
                @if ($scellement)
                    <span class="mention">Procès-verbal scellé le {{ $scellement->date_signature->format('d/m/Y à H:i') }}</span>
                @elseif ($pv)
                    <span class="mention">Procès-verbal validé le {{ $pv->date_validation?->format('d/m/Y') }}</span>
                @else
                    <span class="mention">Procès-verbal en cours de validation</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="verif">
        <tr>
            <td class="qr" style="width:115px"><img src="{{ $qr }}" alt="QR code de vérification"></td>
            <td>
                <strong class="bleu">Vérifier ce document</strong><br>
                <span class="mention">Scannez le QR code, ou saisissez la référence <strong>{{ $reference }}</strong> sur la page de vérification d'Audience+ :</span><br>
                <span class="mono">{{ $urlVerification }}</span><br>
                <span class="mention">Empreinte d'authenticité : <strong>{{ $empreinte }}</strong>@if ($scellement) · empreinte SHA-256 du procès-verbal :@endif</span>
                @if ($scellement)<br><span class="mono">{{ $scellement->hash }}</span>@endif
                <br><span class="mention">Généré le {{ now()->format('d/m/Y à H:i') }}.</span>
            </td>
        </tr>
    </table>

    @if ($pv)
        <div style="page-break-before: always"></div>
        <h2>Annexe · Procès-verbal d'audience (validé)</h2>
        <div class="texte">{{ $pv->contenu }}</div>
    @endif
</body>
</html>
