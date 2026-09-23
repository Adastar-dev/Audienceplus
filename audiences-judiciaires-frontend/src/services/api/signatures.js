import apiClient from './client'

export async function signerDocument({ typeDocument, idDocumentSigne }) {
  const { data } = await apiClient.post('/signatures', {
    type_document: typeDocument,
    id_document_signe: idDocumentSigne,
  })
  return data
}

export async function getSignaturesDocument({ typeDocument, idDocumentSigne }) {
  const { data } = await apiClient.get('/signatures/document', {
    params: { type_document: typeDocument, id_document_signe: idDocumentSigne },
  })
  return data
}
