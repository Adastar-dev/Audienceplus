<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dossier extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_dossier';

    protected $fillable = [
        'numero', 'type', 'statut', 'parties', 'id_tribunal', 'id_tribunal', 'id_justiciable', 'id_avocat', 'id_procureur', 'date_creation',
        'avis_procureur', 'avis_procureur_par', 'avis_procureur_date',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
    ];

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
        if (! in_array($utilisateur->role, ['JUSTICIABLE', 'AVOCAT'], true)) {
            return $query;
        }

        if (! $utilisateur->identite_verifiee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('liaisonsParties', fn ($q) => $q->where('id_utilisateur', $utilisateur->id_utilisateur));
    }
}
