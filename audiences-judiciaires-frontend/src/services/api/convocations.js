import apiClient from './client'

export async function listerConvocations() {
  const { data } = await apiClient.get('/convocations')
  return data
}

export async function confirmerConvocation(id) {
  const { data } = await apiClient.post(`/convocations/${id}/confirmer`)
  return data
}

export async function demanderReport(id, motifReport) {
  const { data } = await apiClient.post(`/convocations/${id}/demander-report`, {
    motif_report: motifReport,
  })
  return data
}

// Avis du greffier (ne tranche pas le report, un juge doit encore valider).
export async function donnerAvisReport(id, avis, { nouvelleDateHeure, reponseGreffier } = {}) {
  const { data } = await apiClient.post(`/convocations/${id}/avis-report`, {
    avis,
    nouvelle_date_heure: nouvelleDateHeure,
    reponse_greffier: reponseGreffier,
  })
  return data
}

// Décision finale, réservée au juge.
export async function approuverReport(id, nouvelleDateHeure) {
  const { data } = await apiClient.post(`/convocations/${id}/approuver-report`, {
    nouvelle_date_heure: nouvelleDateHeure,
  })
  return data
}

export async function refuserReport(id, reponseGreffier) {
  const { data } = await apiClient.post(`/convocations/${id}/refuser-report`, {
    reponse_greffier: reponseGreffier,
  })
  return data
}

export async function relancerConvocation(id) {
  const { data } = await apiClient.post(`/convocations/${id}/relancer`)
  return data
}
