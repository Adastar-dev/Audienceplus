import apiClient from './client'

export async function listerLogs() {
  const { data } = await apiClient.get('/logs')
  return data
}
