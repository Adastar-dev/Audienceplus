import { useEffect } from 'react'
import { Routes, Route, Navigate, useNavigate } from 'react-router-dom'
import ProtectedRoute from './ProtectedRoute'
import DashboardLayout from '../components/layout/DashboardLayout'
import Login from '../pages/auth/Login'
import Inscription from '../pages/auth/Inscription'
import { Role } from '../constants/enums'

import JugeDashboard from '../pages/juge/Dashboard'
import CalendrierAudiences from '../pages/juge/CalendrierAudiences'
import DossiersProgrammes from '../pages/juge/DossiersProgrammes'
import AudienceDetail from '../pages/juge/AudienceDetail'
import SalleAudience from '../pages/juge/SalleAudience'
import ValidationPV from '../pages/juge/ValidationPV'
import ValidationsGreffier from '../pages/juge/ValidationsGreffier'

import GreffierDashboard from '../pages/greffier/Dashboard'
import DossiersList from '../pages/greffier/DossiersList'
import NouveauDossier from '../pages/greffier/NouveauDossier'
import DossierDetail from '../pages/greffier/DossierDetail'
import DepotPieces from '../pages/greffier/DepotPieces'
import NouvelleAudience from '../pages/greffier/NouvelleAudience'
import Convocations from '../pages/greffier/Convocations'
import DemandesDistance from '../pages/greffier/DemandesDistance'
import Emargement from '../pages/greffier/Emargement'
import RedactionPV from '../pages/greffier/RedactionPV'

import ProcureurDashboard from '../pages/procureur/Dashboard'
import DossiersProcureur from '../pages/procureur/Dossiers'
import DecisionsProcureur from '../pages/procureur/Decisions'
import NotificationsProcureur from '../pages/procureur/Notifications'
import MessagerieProcureur from '../pages/procureur/Messagerie'

import AvocatDashboard from '../pages/avocat/Dashboard'
import DossiersAvocat from '../pages/avocat/Dossiers'
import DossierDetailAvocat from '../pages/avocat/DossierDetail'
import ConvocationsAvocat from '../pages/avocat/Convocations'
import HistoriqueAudiences from '../pages/avocat/HistoriqueAudiences'
import Messagerie from '../pages/avocat/Messagerie'
import MessagerieGreffier from '../pages/greffier/Messagerie'
import MessagerieJuge from '../pages/juge/Messagerie'

import JusticiableDashboard from '../pages/justiciable/Dashboard'
import MonDossier from '../pages/justiciable/MonDossier'
import Decisions from '../pages/justiciable/Decisions'
import CasierJudiciaire from '../pages/justiciable/CasierJudiciaire'
import DemandeDistance from '../pages/justiciable/DemandeDistance'
import Notifications from '../pages/justiciable/Notifications'
import SalleAttente from '../pages/justiciable/SalleAttente'
import MessagerieJusticiable from '../pages/justiciable/Messagerie'


import AdminDashboard from '../pages/admin/Dashboard'
import Utilisateurs from '../pages/admin/Utilisateurs'
import Tribunaux from '../pages/admin/Tribunaux'
import ParametresSecurite from '../pages/admin/ParametresSecurite'
import Logs from '../pages/admin/Logs'

import RejoindreAudience from '../pages/shared/RejoindreAudience'

export default function AppRoutes() {
  const navigate = useNavigate()

  // Écoute l'événement émis par client.js sur une réponse 401 - navigation
  // SPA propre au lieu d'un window.location.href qui rechargerait toute la page.
  useEffect(() => {
    function handleSessionExpired() {
      navigate('/connexion', { replace: true })
    }
    window.addEventListener('aj:session-expired', handleSessionExpired)
    return () => window.removeEventListener('aj:session-expired', handleSessionExpired)
  }, [navigate])

  return (
    <Routes>
      <Route path="/connexion" element={<Login />} />
      <Route path="/inscription" element={<Inscription />} />

      <Route element={<ProtectedRoute allowedRoles={[Role.JUGE]} />}>
        <Route element={<DashboardLayout />}>
          <Route path="/juge" element={<JugeDashboard />} />
          <Route path="/juge/audiences" element={<CalendrierAudiences />} />
          <Route path="/juge/dossiers" element={<DossiersProgrammes />} />
          <Route path="/juge/validations" element={<ValidationsGreffier />} />
          <Route path="/juge/messagerie" element={<MessagerieJuge />} />
          <Route path="/juge/audiences/:id" element={<AudienceDetail />} />
          <Route path="/juge/audiences/:id/pv" element={<ValidationPV />} />
        </Route>
        <Route path="/juge/audiences/:id/salle" element={<SalleAudience />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={[Role.GREFFIER]} />}>
        <Route element={<DashboardLayout />}>
          <Route path="/greffier" element={<GreffierDashboard />} />
          <Route path="/greffier/dossiers" element={<DossiersList />} />
          <Route path="/greffier/dossiers/nouveau" element={<NouveauDossier />} />
          <Route path="/greffier/dossiers/:id" element={<DossierDetail />} />
          <Route path="/greffier/dossiers/:id/pieces" element={<DepotPieces />} />
          <Route path="/greffier/audiences/nouvelle" element={<NouvelleAudience />} />
          <Route path="/greffier/convocations" element={<Convocations />} />
          <Route path="/greffier/demandes-distance" element={<DemandesDistance />} />
          <Route path="/greffier/messagerie" element={<MessagerieGreffier />} />
          <Route path="/greffier/audiences/:id/pv" element={<RedactionPV />} />
          <Route path="/greffier/audiences/:id/emargement" element={<Emargement />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute allowedRoles={[Role.PROCUREUR]} />}>
        <Route element={<DashboardLayout />}>
          <Route path="/procureur" element={<ProcureurDashboard />} />
          <Route path="/procureur/dossiers" element={<DossiersProcureur />} />
          <Route path="/procureur/decisions" element={<DecisionsProcureur />} />
          <Route path="/procureur/notifications" element={<NotificationsProcureur />} />
          <Route path="/procureur/messagerie" element={<MessagerieProcureur />} />
        </Route>
        <Route
          path="/procureur/audiences/:id/rejoindre"
          element={<RejoindreAudience backTo="/procureur/dossiers" />}
        />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={[Role.AVOCAT]} />}>
        <Route element={<DashboardLayout />}>
          <Route path="/avocat" element={<AvocatDashboard />} />
          <Route path="/avocat/dossiers" element={<DossiersAvocat />} />
          <Route path="/avocat/dossiers/:id" element={<DossierDetailAvocat />} />
          <Route path="/avocat/convocations" element={<ConvocationsAvocat />} />
          <Route path="/avocat/historique" element={<HistoriqueAudiences />} />
          <Route path="/avocat/messagerie" element={<Messagerie />} />
        </Route>
        <Route
          path="/avocat/audiences/:id/rejoindre"
          element={<RejoindreAudience backTo="/avocat/dossiers" />}
        />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={[Role.JUSTICIABLE]} />}>
        <Route element={<DashboardLayout />}>
          <Route path="/justiciable" element={<JusticiableDashboard />} />
          <Route path="/justiciable/dossier" element={<MonDossier />} />
          <Route path="/justiciable/decisions" element={<Decisions />} />
          <Route path="/justiciable/casier" element={<CasierJudiciaire />} />
          <Route path="/justiciable/demande-distance" element={<DemandeDistance />} />
          <Route path="/justiciable/notifications" element={<Notifications />} />
          <Route path="/justiciable/messagerie" element={<MessagerieJusticiable />} />
        </Route>
        <Route path="/justiciable/salle-attente/:id" element={<SalleAttente />} />
        <Route
          path="/justiciable/audiences/:id/rejoindre"
          element={<RejoindreAudience backTo="/justiciable" />}
        />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={[Role.ADMINISTRATEUR]} />}>
        <Route element={<DashboardLayout />}>
          <Route path="/admin" element={<AdminDashboard />} />
          <Route path="/admin/utilisateurs" element={<Utilisateurs />} />
          <Route path="/admin/tribunaux" element={<Tribunaux />} />
          <Route path="/admin/securite" element={<ParametresSecurite />} />
          <Route path="/admin/logs" element={<Logs />} />
        </Route>
      </Route>

      <Route path="/" element={<Navigate to="/connexion" replace />} />
      <Route path="*" element={<Navigate to="/connexion" replace />} />
    </Routes>
  )
}
