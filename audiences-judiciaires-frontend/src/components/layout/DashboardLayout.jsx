import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { LogOut, Scale, Menu, X } from 'lucide-react'
import { useAuth } from '../../context/AuthContext'
import { NAV_BY_ROLE } from '../../constants/navigation'
import { ROLE_LABELS } from '../../constants/enums'

export default function DashboardLayout() {
  const { user, role, logout } = useAuth()
  const links = NAV_BY_ROLE[role] ?? []
  const [menuOuvert, setMenuOuvert] = useState(false)

  const sidebarContent = (
    <>
      <div className="flex items-center gap-2 px-5 py-5 border-b border-navy-700">
        <Scale size={20} className="text-gold-600" />
        <span className="font-display text-sm leading-tight">
          Audience+
        </span>
        <button
          type="button"
          onClick={() => setMenuOuvert(false)}
          className="ml-auto text-navy-100 hover:text-white lg:hidden"
          aria-label="Fermer le menu"
        >
          <X size={20} />
        </button>
      </div>

      <nav className="flex-1 py-4 overflow-y-auto">
        {links.map((link) => (
          <NavLink
            key={link.to}
            to={link.to}
            end={link.to === `/${role.toLowerCase()}`}
            onClick={() => setMenuOuvert(false)}
            className={({ isActive }) =>
              `block px-5 py-2.5 text-sm transition-colors ${
                isActive
                  ? 'bg-navy-800 text-white border-l-2 border-gold-600'
                  : 'text-navy-100 hover:bg-navy-800/60 border-l-2 border-transparent'
              }`
            }
          >
            {link.label}
          </NavLink>
        ))}
      </nav>

      <div className="px-5 py-4 border-t border-navy-700">
        <p className="text-xs text-navy-100">{user?.nom}</p>
        <p className="text-xs text-gold-600 mb-3">{ROLE_LABELS[role]}</p>
        <button
          onClick={logout}
          className="flex items-center gap-1.5 text-xs text-navy-100 hover:text-white transition-colors"
        >
          <LogOut size={14} />
          Se déconnecter
        </button>
      </div>
    </>
  )

  return (
    <div className="min-h-screen flex bg-paper">
      {/* Sidebar : fixe sur desktop (lg+), panneau coulissant sur mobile/tablette */}
      <aside className="hidden lg:flex w-64 shrink-0 bg-navy-900 text-white flex-col">
        {sidebarContent}
      </aside>

      {menuOuvert && (
        <div
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          onClick={() => setMenuOuvert(false)}
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-64 bg-navy-900 text-white flex flex-col transition-transform duration-200 lg:hidden ${
          menuOuvert ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {sidebarContent}
      </aside>

      <div className="flex-1 min-w-0 flex flex-col">
        <header className="lg:hidden flex items-center gap-3 px-4 py-3 bg-navy-900 text-white">
          <button
            type="button"
            onClick={() => setMenuOuvert(true)}
            aria-label="Ouvrir le menu"
          >
            <Menu size={22} />
          </button>
          <Scale size={18} className="text-gold-600" />
          <span className="font-display text-sm">Audience+</span>
        </header>

        <main className="flex-1 min-w-0">
          <div className="max-w-5xl mx-auto px-4 sm:px-8 py-6 sm:py-8">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
