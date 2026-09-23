// Badge générique pour afficher un statut. `tone` détermine la couleur ;
// on garde un mapping explicite plutôt que de deviner à partir du texte.
const TONES = {
  neutral: 'bg-slate-200 text-slate-600',
  info: 'bg-navy-100 text-navy-900',
  success: 'bg-success-100 text-success-700',
  warning: 'bg-gold-100 text-gold-600',
  danger: 'bg-danger-100 text-danger-700',
}

export default function Badge({ children, tone = 'neutral' }) {
  return (
    <span
      className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${TONES[tone]}`}
    >
      {children}
    </span>
  )
}
