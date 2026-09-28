import apiClient from './client'

export const LIBELLES_DECISION = {
  JUGEMENT: 'Jugement',
  RENVOI: 'Renvoi',
  DELIBERE: 'Mise en délibéré',
}

// Une décision est rendue dès que le juge l'a enregistrée et que l'audience
// n'est plus en cours (jugement, renvoi ou mise en délibéré).
export function decisionRendue(audience) {
  return Boolean(audience.type_decision) && audience.statut !== 'EN_COURS'
}

export async function telechargerPdf(chemin, nomFichier) {
  const { data } = await apiClient.get(chemin, { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const lien = document.createElement('a')
  lien.href = url
  lien.download = nomFichier
  lien.click()
  URL.revokeObjectURL(url)
}

export function telechargerDecision(audience) {
  return telechargerPdf(
    `/audiences/${audience.id_audience}/decision/pdf`,
    `decision_${audience.dossier?.numero ?? audience.id_audience}.pdf`,
  )
}
