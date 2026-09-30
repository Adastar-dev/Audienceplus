<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dossier extends Model
{
    // Procédures gracieuses : introduites par requête, sans adversaire. Le
    // dossier n'a qu'une partie, le requérant (enregistré comme demandeur).
    public const TYPES_SANS_DEFENDEUR = [
        'CHANGEMENT_NOM',
        'RECTIFICATION_ACTE',
        'ADOPTION',
        'TUTELLE',
        'DECLARATION_ABSENCE_DECES',
        'EMANCIPATION',
    ];

    // Dossiers communiqués au ministère public : seuls ceux-ci sont visibles
    // par le procureur, qui peut y donner son avis.
    public const TYPES_AVEC_MINISTERE_PUBLIC = [
        'ADOPTION',
        'FILIATION',
        'TUTELLE',
    ];

    public static function communiqueAuParquet(string $type): bool
    {
        return in_array($type, self::TYPES_AVEC_MINISTERE_PUBLIC, true);
    }

    public static function aUnDefendeur(string $type): bool
    {
        return ! in_array($type, self::TYPES_SANS_DEFENDEUR, true);
    }

    protected $primaryKey = 'id_dossier';

    protected $fillable = [
        'numero', 'type', 'statut', 'parties', 'id_tribunal', 'id_procureur', 'date_creation',
        'avis_procureur', 'avis_procureur_par', 'avis_procureur_date', 'date_archivage',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'date_archivage' => 'datetime',
    ];

    // Juge, greffier et procureur ne voient que les dossiers de leur tribunal
    // (besoin d'en connaître) ; un juge voit aussi ceux dont il préside une
    // audience. Un compte sans tribunal de rattachement n'est pas filtré.
    public const ROLES_JURIDICTION = ['JUGE', 'GREFFIER', 'PROCUREUR'];

    public function estArchive(): bool
    {
        return $this->statut === 'ARCHIVE';
    }

    public function archiver(): void
    {
        $this->update(['statut' => 'ARCHIVE', 'date_archivage' => now()]);
    }

    public function tribunal()
    {
        return $this->belongsTo(Tribunal::class, 'id_tribunal', 'id_tribunal');
    }

    public function procureurQuiADonneAvis()
    {
        return $this->belongsTo(Utilisateur::class, 'avis_procureur_par', 'id_utilisateur');
    }

    // Procureur par defaut du dossier (supplence) - n'importe quel procureur
    // reste libre de le consulter et d'y donner un avis, voir estAccessiblePar.
    public function procureurAssigne()
    {
        return $this->belongsTo(Utilisateur::class, 'id_procureur', 'id_utilisateur');
    }

    public function pieces()
    {
        return $this->hasMany(Piece::class, 'id_dossier', 'id_dossier');
    }

    public function audiences()
    {
        return $this->hasMany(Audience::class, 'id_dossier', 'id_dossier');
    }

    // Nommee differemment de la colonne texte 'parties' (ex: "Dupont c. Martin")
    // pour eviter qu'un with()/load() sur cette relation n'ecrase cet attribut
    // dans le JSON renvoye au frontend.
    public function liaisonsParties()
    {
        return $this->hasMany(PartieDossier::class, 'id_dossier', 'id_dossier');
    }

    public function utilisateursAConvoquer()
    {
        return $this->liaisonsParties()
            ->with('utilisateur')
            ->get()
            ->pluck('utilisateur')
            ->filter()
            ->unique('id_utilisateur');
    }

    // Le compte doit avoir ete verifie par le greffe avant de donner acces a
    // quoi que ce soit, y compris pour un Justiciable - pas seulement un
    // Avocat. Sans ca, un fraudeur pourrait s'inscrire avec le numero de CNI
    // d'une autre personne (declaratif a l'inscription, jamais verifie -
    // voir AuthController::inscription) : comme ce numero est unique en
    // base, il "prendrait" ce numero avant le vrai titulaire, et le greffier
    // qui recherche/relie un justiciable par CNI (rechercherJusticiables)
    // relierait alors le dossier au compte frauduleux sans le savoir.
    public function estAccessiblePar(Utilisateur $utilisateur): bool
    {
        if (in_array($utilisateur->role, self::ROLES_JURIDICTION, true)) {
            if ($utilisateur->role === 'PROCUREUR' && ! self::communiqueAuParquet($this->type)) {
                return false;
            }

            return ! $utilisateur->id_tribunal
                || (int) $this->id_tribunal === (int) $utilisateur->id_tribunal
                || $this->audiences()->where('id_juge', $utilisateur->id_utilisateur)->exists();
        }

        if (! in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT'], true)) {
            return true;
        }

        if (! $utilisateur->identite_verifiee) {
            return false;
        }

        return $this->liaisonsParties()->where('id_utilisateur', $utilisateur->id_utilisateur)->exists();
    }

    public function scopeVisiblesPar($query, Utilisateur $utilisateur)
    {
        if (in_array($utilisateur->role, self::ROLES_JURIDICTION, true)) {
            if ($utilisateur->role === 'PROCUREUR') {
                $query->whereIn('type', self::TYPES_AVEC_MINISTERE_PUBLIC);
            }

            if ($utilisateur->id_tribunal) {
                $query->where(fn ($q) => $q
                    ->where('id_tribunal', $utilisateur->id_tribunal)
                    ->orWhereHas('audiences', fn ($a) => $a->where('id_juge', $utilisateur->id_utilisateur)));
            }

            return $query;
        }

        if (! in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT'], true)) {
            return $query;
        }

        if (! $utilisateur->identite_verifiee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('liaisonsParties', fn ($q) => $q->where('id_utilisateur', $utilisateur->id_utilisateur));
    }
}
