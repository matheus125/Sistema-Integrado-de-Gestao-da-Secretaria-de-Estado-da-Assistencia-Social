const routes = [
  {
    path: '/login',
    component: () => import('@/pages/LoginPage.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', component: () => import('@/pages/IndexPage.vue'), meta: { permission: 'dashboard.visualizar' } },
      { path: 'administracao', component: () => import('@/pages/AdminPage.vue'), meta: { permission: 'administracao.visualizar' } },
      { path: 'transporte', component: () => import('@/pages/TransportPage.vue'), meta: { permission: 'transporte.visualizar' } },
      { path: 'materiais', component: () => import('@/pages/MaterialsPage.vue'), meta: { permission: 'materiais-patrimonio.visualizar' } },
      { path: 'informatica', component: () => import('@/pages/ItHelpDeskPage.vue'), meta: { permission: 'informatica.visualizar' } },
      { path: 'informatica/meus-chamados', component: () => import('@/pages/ItHelpDeskPage.vue'), meta: { permission: 'informatica.visualizar', requesterOnly: true } },
      { path: 'informatica/manutencao-preventiva', component: () => import('@/pages/ItPreventiveMaintenancePage.vue'), meta: { permission: 'informatica.visualizar' } },
      { path: 'auditoria', component: () => import('@/pages/AuditPage.vue'), meta: { permission: 'auditoria.visualizar' } },
      { path: 'relatorios', component: () => import('@/pages/ReportsPage.vue'), meta: { permission: 'relatorios.visualizar' } },
      { path: 'configuracoes', component: () => import('@/pages/SettingsPage.vue'), meta: { permission: 'configuracoes.visualizar' } }
    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  }
]

export default routes
