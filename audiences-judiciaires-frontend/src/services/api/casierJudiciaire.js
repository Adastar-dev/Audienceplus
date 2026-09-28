import apiClient from './client'
import { telechargerPdf } from './decisions'

export async function listerDemandes() {
  const { data } = await apiClient.get('/casier-judiciaire')
  return data
}

export async function demanderCasier() {
  const { data } = await apiClient.post('/casier-judiciaire')
  return data
}

export function telechargerCasier(casier) {
  return telechargerPdf(`/casier-judiciaire/${casier.id_casier}/pdf`, `casier_${casier.qr_code}.pdf`)
}
