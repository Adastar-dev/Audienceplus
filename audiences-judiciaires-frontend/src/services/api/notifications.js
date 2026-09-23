import apiClient from './client'

export async function listerNotifications() {
  const { data } = await apiClient.get('/notifications')
  return data
}

export async function accuserReceptionNotification(id) {
  const { data } = await apiClient.post(`/notifications/${id}/accuser-reception`)
  return data
}
