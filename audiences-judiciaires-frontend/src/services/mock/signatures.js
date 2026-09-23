import { TypeDocument } from '../../constants/enums'

export const signatures = []

export function addSignature({ utilisateur, idDocumentSigne }) {
  signatures.push({
    id_signature: Date.now(),
    utilisateur,
    type_document: TypeDocument.PROCES_VERBAL,
    id_document_signe: idDocumentSigne,
    date_signature: new Date().toISOString(),
  })
}

export function getSignatures(idDocumentSigne) {
  return signatures.filter(
    (s) => s.type_document === TypeDocument.PROCES_VERBAL && s.id_document_signe === idDocumentSigne,
  )
}
