export const piecesByDossier = {
  1: [
    { id_piece: 1, nom: 'Contrat_de_bail.pdf', type_fichier: 'pdf', valide: true, date_depot: '2026-07-10' },
    { id_piece: 2, nom: 'Constat_huissier.pdf', type_fichier: 'pdf', valide: false, date_depot: '2026-08-20' },
  ],
  2: [
    { id_piece: 3, nom: 'Rapport_police.pdf', type_fichier: 'pdf', valide: true, date_depot: '2026-07-06' },
  ],
  3: [],
  4: [
    { id_piece: 4, nom: 'Releve_compte.pdf', type_fichier: 'pdf', valide: true, date_depot: '2026-05-15' },
  ],
}

export function getPieces(idDossier) {
  return piecesByDossier[idDossier] ?? []
}
