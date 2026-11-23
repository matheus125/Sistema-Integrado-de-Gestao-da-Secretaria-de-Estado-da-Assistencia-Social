<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Gerencia de Informatica</div>
        <h1>{{ requesterOnly ? 'Meus Chamados' : 'Help Desk e SLA' }}</h1>
        <p>{{ requesterOnly ? 'Acompanhe seus chamados, respostas, anexos, reaberturas e avaliacoes.' : 'Atendimento tecnico com triagem, distribuicao de chamados, historico de mensagens e controle de prazo.' }}</p>
      </div>
      <div class="action-row">
        <q-btn v-if="canCreate" color="primary" icon="add_comment" label="Abrir chamado" unelevated @click="openTicket()" />
        <q-btn color="secondary" icon="refresh" label="Atualizar" unelevated :loading="loading" @click="loadAll" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-tabs v-model="tab" class="it-tabs" dense align="left" active-color="primary" indicator-color="primary" @update:model-value="changeTab">
        <q-tab v-if="!requesterOnly" name="dashboard" icon="dashboard" label="Painel" />
        <q-tab name="tickets" icon="support_agent" :label="requesterOnly ? 'Meus chamados' : 'Chamados'" />
        <q-tab v-if="!requesterOnly" name="kanban" icon="view_kanban" label="Kanban" />
        <q-tab v-if="canViewCategories" name="categories" icon="category" label="Categorias" />
        <q-tab v-if="canEdit" name="knowledge" icon="menu_book" label="Conhecimento" />
        <q-tab v-if="canEdit" name="teams" icon="groups" label="Equipes" />
        <q-tab v-if="canEdit" name="response-templates" icon="text_snippet" label="Respostas" />
        <q-tab v-if="canEdit" name="automations" icon="bolt" label="Automações" />
        <q-tab v-if="canEdit" name="service-catalog" icon="list_alt" label="Catálogo" />
        <q-tab v-if="canEdit" name="approvals" icon="verified_user" label="Aprovações" />
        <q-tab v-if="canEdit" name="checklists" icon="checklist" label="Checklists" />
        <q-tab v-if="canManageSla" name="sla" icon="timer" label="SLA" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated class="module-panels">
        <q-tab-panel v-if="!requesterOnly" name="dashboard">
          <div class="row items-center justify-between q-mb-md">
            <div>
              <div class="text-h6">Dashboard operacional</div>
              <div class="text-caption text-grey-7">Indicadores, desempenho do técnico e reincidências dos últimos 90 dias.</div>
            </div>
            <q-btn flat round icon="refresh" aria-label="Atualizar dashboard do Help Desk" :loading="dashboardLoading" @click="loadDashboard" />
          </div>
          <div class="metric-grid">
            <q-card v-for="item in dashboardCards" :key="item.key" flat bordered class="metric-card">
              <q-card-section>
                <q-icon :name="item.icon" :color="item.color" size="28px" />
                <div class="metric-value">{{ dashboard.indicators?.[item.key] ?? 0 }}{{ item.suffix || '' }}</div>
                <div class="metric-label">{{ item.label }}</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="it-dashboard-grid q-mt-md">
            <q-card flat bordered>
              <q-card-section class="text-subtitle2">Distribuição por status</q-card-section>
              <q-list dense separator><q-item v-for="row in (dashboard.charts?.by_status || [])" :key="row.label"><q-item-section>{{ labelFor(row.label) }}</q-item-section><q-item-section side>{{ row.value }}</q-item-section></q-item></q-list>
            </q-card>
            <q-card flat bordered>
              <q-card-section class="text-subtitle2">Aging do backlog</q-card-section>
              <q-list dense separator><q-item v-for="(value, label) in (dashboard.indicators?.aging || {})" :key="label"><q-item-section>{{ label }}</q-item-section><q-item-section side>{{ value }}</q-item-section></q-item></q-list>
            </q-card>
            <q-card flat bordered>
              <q-card-section class="text-subtitle2">Meu desempenho</q-card-section>
              <q-list dense separator><q-item><q-item-section>Chamados ativos</q-item-section><q-item-section side>{{ dashboard.technician?.my_tickets || 0 }}</q-item-section></q-item><q-item><q-item-section>Urgentes</q-item-section><q-item-section side>{{ dashboard.technician?.urgent || 0 }}</q-item-section></q-item><q-item><q-item-section>Resolvidos hoje</q-item-section><q-item-section side>{{ dashboard.technician?.resolved_today || 0 }}</q-item-section></q-item><q-item><q-item-section>Avaliação média</q-item-section><q-item-section side>{{ dashboard.technician?.avg_rating || 0 }}</q-item-section></q-item></q-list>
            </q-card>
          </div>
          <q-table class="q-mt-md" flat bordered title="Próximos chamados recomendados" :rows="dashboard.technician?.recommended || []" :columns="recommendedColumns" row-key="id" :loading="dashboardLoading" no-data-label="Nenhum chamado recomendado" />
          <q-table class="q-mt-md" flat bordered title="Recorrências identificadas" :rows="recurrenceRows" :columns="recurrenceColumns" row-key="id" :loading="dashboardLoading" no-data-label="Nenhuma reincidência no período" />
        </q-tab-panel>
        <q-tab-panel name="tickets">
          <div class="metric-grid">
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="forum" color="primary" size="28px" />
                <div class="metric-value">{{ summary.open || 0 }}</div>
                <div class="metric-label">Chamados abertos</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="notifications" color="negative" size="28px" />
                <div class="metric-value">{{ summary.overdue || 0 }}</div>
                <div class="metric-label">SLA vencido</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="event_available" color="warning" size="28px" />
                <div class="metric-value">{{ summary.due_today || 0 }}</div>
                <div class="metric-label">Vencem hoje</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="task_alt" color="positive" size="28px" />
                <div class="metric-value">{{ summary.resolved_month || 0 }}</div>
                <div class="metric-label">Resolvidos no mes</div>
              </q-card-section>
            </q-card>
          </div>

          <q-table
            flat
            bordered
            title="Chamados"
            :rows="tickets"
            :columns="ticketColumns"
            row-key="id"
            v-model:selected="selectedTickets"
            :selection="canEdit ? 'multiple' : 'none'"
            v-model:pagination="ticketPagination"
            :rows-per-page-options="[5, 10, 15, 25, 50]"
            @request="onTicketRequest"
            :loading="loading"
            no-data-label="Nenhum chamado registrado"
            aria-label="Lista de chamados"
          >
        <template #loading>
          <q-inner-loading showing color="primary" />
          <q-tr v-for="row in 5" :key="`ticket-skeleton-${row}`">
            <q-td v-for="column in ticketColumns" :key="column.name"><q-skeleton type="text" /></q-td>
          </q-tr>
        </template>
        <template #no-data>
          <div class="empty-state">
            <q-icon name="inbox" size="42px" color="grey-7" />
            <div class="text-subtitle2">Nenhum chamado encontrado</div>
            <div class="text-caption text-grey-7">Ajuste os filtros ou abra um novo chamado.</div>
            <q-btn v-if="hasActiveFilters" flat color="primary" label="Limpar filtros" icon="filter_alt_off" @click="clearFilters" />
          </div>
        </template>
        <template #top>
          <div class="it-filters">
            <q-input v-model="filters.search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadAll">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select
              v-model="filters.status"
              dense
              outlined
              clearable
              label="Status"
              :options="statusOptions"
              emit-value
              map-options
              @update:model-value="loadAll"
            />
            <q-select v-model="filters.type" dense outlined clearable label="Tipo" :options="typeOptions" emit-value map-options @update:model-value="loadAll" />
            <q-select v-model="filters.priority" dense outlined clearable label="Prioridade" :options="priorityOptions" emit-value map-options @update:model-value="loadAll" />
            <q-select v-model="filters.category_id" dense outlined clearable label="Categoria" :options="categoryOptions" emit-value map-options @update:model-value="loadAll" />
            <q-select v-model="filters.department_id" dense outlined clearable label="Setor" :options="departmentOptions" emit-value map-options @update:model-value="loadAll" />
            <q-select v-model="filters.assigned_to" dense outlined clearable label="Tecnico" :options="technicianOptions" emit-value map-options @update:model-value="loadAll" />
            <q-select v-model="filters.source" dense outlined clearable label="Origem" :options="sourceOptions" emit-value map-options @update:model-value="loadAll" />
            <q-select v-model="filters.sla" dense outlined clearable label="SLA" :options="slaOptions" emit-value map-options @update:model-value="loadAll" />
            <q-input v-model="filters.date_from" dense outlined type="date" label="De" @update:model-value="loadAll" />
            <q-input v-model="filters.date_to" dense outlined type="date" label="Ate" @update:model-value="loadAll" />
            <q-btn flat round icon="filter_alt" aria-label="Limpar filtros de chamados" @click="clearFilters">
              <q-tooltip>Limpar filtros</q-tooltip>
            </q-btn>
          </div>
        </template>

        <template #body-cell-priority="props">
          <q-td :props="props">
            <q-badge :color="priorityColor(props.row.priority)" outline>{{ labelFor(props.row.priority) }}</q-badge>
          </q-td>
        </template>

        <template #body-cell-type="props">
          <q-td :props="props">
            <q-badge :color="typeMeta(props.row.type).color" outline>
              <q-icon :name="typeMeta(props.row.type).icon" size="14px" class="q-mr-xs" />
              {{ typeMeta(props.row.type).label }}
            </q-badge>
          </q-td>
        </template>

        <template #body-cell-status="props">
          <q-td :props="props">
            <q-badge :color="statusColor(props.row.status)" outline>{{ statusLabel(props.row.status) }}</q-badge>
          </q-td>
        </template>

        <template #body-cell-sla_due_at="props">
          <q-td :props="props">
            <q-badge :color="slaColor(props.row.sla_due_at, props.row.status)" outline>
              {{ formatDateTime(props.row.sla_due_at) }}
            </q-badge>
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat round dense icon="forum" aria-label="Abrir histórico e atendimento" @click="inspectTicket(props.row)">
              <q-tooltip>Historico e atendimento</q-tooltip>
            </q-btn>
            <q-btn v-if="canEdit" flat round dense icon="edit" aria-label="Editar chamado" @click="openTicket(props.row)">
              <q-tooltip>Editar chamado</q-tooltip>
            </q-btn>
          </q-td>
        </template>
          </q-table>
          <div v-if="canEdit && selectedTickets.length" class="row items-center q-gutter-sm q-mt-sm">
            <q-badge color="primary">{{ selectedTickets.length }} selecionado(s)</q-badge>
            <q-select v-model="bulkActionForm.action" dense outlined label="Ação em massa" :options="bulkActionOptions" emit-value map-options />
            <q-select v-if="bulkActionForm.action !== 'close'" v-model="bulkActionForm.value" dense outlined label="Valor" :options="bulkValueOptions" emit-value map-options clearable />
            <q-btn color="primary" icon="done_all" label="Aplicar" :disable="bulkActionForm.action !== 'close' && !bulkActionForm.value" @click="applyBulkAction" />
          </div>
        </q-tab-panel>

        <q-tab-panel v-if="!requesterOnly" name="kanban">
          <div class="it-filters q-mb-md">
            <q-input v-model="filters.search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="applyKanbanFilters">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="filters.status" dense outlined clearable label="Status" :options="statusOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.type" dense outlined clearable label="Tipo" :options="typeOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.priority" dense outlined clearable label="Prioridade" :options="priorityOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.category_id" dense outlined clearable label="Categoria" :options="categoryOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.department_id" dense outlined clearable label="Setor" :options="departmentOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.assigned_to" dense outlined clearable label="Tecnico" :options="technicianOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.sla" dense outlined clearable label="SLA" :options="slaOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-select v-model="filters.source" dense outlined clearable label="Origem" :options="sourceOptions" emit-value map-options @update:model-value="applyKanbanFilters" />
            <q-btn flat round icon="filter_alt" aria-label="Limpar filtros do Kanban" @click="clearFilters">
              <q-tooltip>Limpar filtros</q-tooltip>
            </q-btn>
          </div>
          <it-kanban-board
            :tickets="tickets"
            :statuses="statusOptions"
            :types="typeOptions"
            :can-edit="canEdit"
            :loading="loading"
            @open-ticket="inspectTicket"
            @move-ticket="moveKanbanTicket"
          />
          <div class="kanban-pagination" aria-label="Paginação do Kanban">
            <div class="text-caption text-grey-7" aria-live="polite">
              Exibindo {{ kanbanPagination.from || 0 }}–{{ kanbanPagination.to || 0 }} de {{ kanbanPagination.rowsNumber }} chamados
            </div>
            <q-select
              :model-value="kanbanPagination.rowsPerPage"
              dense
              outlined
              emit-value
              map-options
              label="Cartões por página"
              :options="kanbanPageSizeOptions"
              @update:model-value="changeKanbanPageSize"
            />
            <q-pagination
              :model-value="kanbanPagination.page"
              :max="kanbanPageCount"
              :max-pages="7"
              direction-links
              boundary-links
              aria-label="Navegar pelas páginas do Kanban"
              @update:model-value="changeKanbanPage"
            />
          </div>
        </q-tab-panel>

        <q-tab-panel v-if="canViewCategories" name="categories">
          <q-table
            flat
            bordered
            title="Categorias"
            :rows="categories"
            :columns="categoryColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhuma categoria cadastrada"
          >
            <template #top-right>
              <div class="row q-gutter-sm">
                <q-input v-model="categorySearch" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadCategories">
                  <template #prepend><q-icon name="search" /></template>
                </q-input>
                <q-btn v-if="canCreateCategory" color="primary" icon="add" label="Nova categoria" unelevated @click="openCategory()" />
              </div>
            </template>
            <template #body-cell-name="props">
              <q-td :props="props">
                <div class="row items-center q-gutter-sm no-wrap">
                  <q-icon :name="props.row.icon || 'category'" :color="props.row.color || 'primary'" />
                  <span>{{ props.row.name }}</span>
                </div>
              </q-td>
            </template>
            <template #body-cell-active="props">
              <q-td :props="props">
                <q-badge :color="props.row.active ? 'positive' : 'grey'" outline>{{ props.row.active ? 'ativo' : 'inativo' }}</q-badge>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEditCategory" flat round dense icon="edit" aria-label="Editar categoria" @click="openCategory(props.row)">
                  <q-tooltip>Editar categoria</q-tooltip>
                </q-btn>
                <q-btn v-if="canDeleteCategory" flat round dense icon="delete" color="negative" aria-label="Excluir categoria" @click="removeCategory(props.row)">
                  <q-tooltip>Excluir categoria</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="knowledge">
          <q-table flat bordered title="Base de conhecimento" :rows="knowledgeArticles" :columns="knowledgeColumns" row-key="id" :loading="loading">
            <template #top-right><q-btn flat round icon="refresh" aria-label="Atualizar base de conhecimento" :loading="loading" @click="loadKnowledge" /></template>
            <template #body-cell-public="props">
              <q-td :props="props"><q-toggle :model-value="props.row.public" @update:model-value="value => publishArticle(props.row, value)" /></q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="teams">
          <q-table flat bordered title="Filas e equipes" :rows="supportTeams" :columns="teamColumns" row-key="id" :loading="loading">
            <template #top-right><q-btn flat round icon="refresh" aria-label="Atualizar equipes" :loading="loading" @click="loadTeams" /></template>
            <template #body-cell-technicians="props"><q-td :props="props">{{ (props.row.technicians || []).map(item => item.name).join(', ') || 'Sem tecnicos' }}</q-td></template>
            <template #body-cell-categories="props"><q-td :props="props">{{ (props.row.categories || []).map(item => item.name).join(', ') || 'Todas' }}</q-td></template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="response-templates">
          <q-table flat bordered title="Templates de resposta" :rows="responseTemplates" :columns="responseTemplateColumns" row-key="id" :loading="loading" no-data-label="Nenhum template cadastrado">
            <template #top-right><q-btn color="primary" icon="add" label="Novo template" unelevated @click="openResponseTemplate()" /></template>
            <template #body-cell-scope="props"><q-td :props="props">{{ responseTemplateScope(props.row) }}</q-td></template>
            <template #body-cell-active="props"><q-td :props="props"><q-badge :color="props.row.active ? 'positive' : 'grey'" outline>{{ props.row.active ? 'ativo' : 'inativo' }}</q-badge></q-td></template>
            <template #body-cell-actions="props"><q-td :props="props"><q-btn flat round dense icon="edit" aria-label="Editar template de resposta" @click="openResponseTemplate(props.row)" /></q-td></template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="automations">
          <q-table flat bordered title="Regras de automação" :rows="automationRules" :columns="automationColumns" row-key="id" :loading="loading" no-data-label="Nenhuma regra cadastrada">
            <template #top-right><q-btn color="primary" icon="add" label="Nova regra" unelevated @click="openAutomationRule()" /></template>
            <template #body-cell-conditions="props"><q-td :props="props">{{ formatAutomationJson(props.row.conditions) }}</q-td></template>
            <template #body-cell-actions="props"><q-td :props="props">{{ formatAutomationJson(props.row.actions) }}</q-td></template>
            <template #body-cell-active="props"><q-td :props="props"><q-toggle :model-value="props.row.active" @update:model-value="value => toggleAutomationRule(props.row, value)" /></q-td></template>
            <template #body-cell-edit="props"><q-td :props="props"><q-btn flat round dense icon="edit" aria-label="Editar regra de automação" @click="openAutomationRule(props.row)" /></q-td></template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="service-catalog">
          <q-table flat bordered title="Catálogo de serviços" :rows="serviceCatalog" :columns="serviceCatalogColumns" row-key="id" :loading="loading" no-data-label="Nenhum serviço cadastrado">
            <template #top-right><q-btn color="primary" icon="add" label="Novo serviço" unelevated @click="openServiceCatalog()" /></template>
            <template #body-cell-category="props"><q-td :props="props">{{ props.row.category?.name || 'Sem categoria' }}</q-td></template>
            <template #body-cell-active="props"><q-td :props="props"><q-badge :color="props.row.active ? 'positive' : 'grey'" outline>{{ props.row.active ? 'ativo' : 'inativo' }}</q-badge></q-td></template>
            <template #body-cell-actions="props"><q-td :props="props"><q-btn flat round dense icon="edit" aria-label="Editar serviço" @click="openServiceCatalog(props.row)" /></q-td></template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="approvals">
          <q-table flat bordered title="Aprovações de serviços" :rows="approvals" :columns="approvalColumns" row-key="id" :loading="loading" no-data-label="Nenhuma aprovação pendente">
            <template #body-cell-ticket="props"><q-td :props="props">#{{ props.row.ticket?.id }} — {{ props.row.ticket?.requester_name }}</q-td></template>
            <template #body-cell-status="props"><q-td :props="props"><q-badge :color="props.row.status === 'pendente' ? 'warning' : (props.row.status === 'aprovado' ? 'positive' : 'negative')" outline>{{ props.row.status }}</q-badge></q-td></template>
            <template #body-cell-actions="props"><q-td :props="props"><div v-if="props.row.status === 'pendente'" class="row q-gutter-xs"><q-btn dense color="positive" icon="check" label="Aprovar" @click="decideApproval(props.row, 'aprovado')" /><q-btn dense color="negative" icon="close" label="Rejeitar" outline @click="decideApproval(props.row, 'rejeitado')" /></div></q-td></template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canEdit" name="checklists">
          <q-table flat bordered title="Templates de checklist" :rows="checklistTemplates" :columns="checklistColumns" row-key="id" :loading="loading">
            <template #top-right><q-btn color="primary" icon="add" label="Novo template" unelevated @click="openChecklistTemplate()" /></template>
            <template #body-cell-items="props"><q-td :props="props">{{ (props.row.items || []).map(item => item.label).join(', ') }}</q-td></template>
            <template #body-cell-actions="props"><q-td :props="props"><q-btn flat round dense icon="edit" aria-label="Editar checklist" @click="openChecklistTemplate(props.row)" /></q-td></template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel v-if="canManageSla" name="sla">
          <ItSlaCalendars :calendars="slaCalendars" :timezone="slaTimezone" @saved="loadSlaRules" />
          <q-table
            flat
            bordered
            title="Regras de SLA"
            :rows="slaRules"
            :columns="slaColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhuma regra de SLA cadastrada"
          >
            <template #top-right>
              <div class="row q-gutter-sm">
                <q-btn flat round icon="refresh" aria-label="Atualizar regras de SLA" :loading="loading" @click="loadSlaRules">
                  <q-tooltip>Atualizar SLAs</q-tooltip>
                </q-btn>
                <q-btn color="primary" icon="add" label="Nova regra" unelevated @click="openSlaRule()" />
              </div>
            </template>

            <template #body-cell-scope="props">
              <q-td :props="props">
                <div class="column">
                  <span>{{ slaScope(props.row) }}</span>
                  <span class="text-caption text-grey-7">{{ props.row.calendar?.name || 'Sem calendario' }}</span>
                </div>
              </q-td>
            </template>

            <template #body-cell-times="props">
              <q-td :props="props">
                <q-badge outline color="primary">1a resposta {{ props.row.first_response_minutes }} min</q-badge>
                <q-badge outline color="secondary" class="q-ml-xs">Resolucao {{ props.row.resolution_minutes }} min</q-badge>
              </q-td>
            </template>

            <template #body-cell-active="props">
              <q-td :props="props">
                <q-badge :color="props.row.active ? 'positive' : 'grey'" outline>{{ props.row.active ? 'ativo' : 'inativo' }}</q-badge>
                <q-badge v-if="props.row.is_fallback" color="warning" outline class="q-ml-xs">fallback</q-badge>
              </q-td>
            </template>

            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn flat round dense icon="edit" aria-label="Editar regra de SLA" @click="openSlaRule(props.row)">
                  <q-tooltip>Editar regra</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>
      </q-tab-panels>
    </section>

    <q-dialog v-model="ticketDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ ticketForm.id ? 'Editar chamado' : 'Abrir chamado' }}</div>
          <q-btn flat round dense icon="close" aria-label="Fechar chamado" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveTicket">
          <q-card-section class="form-grid">
            <q-input v-model="ticketForm.requester_name" v-bind="serverErrorProps('requester_name')" outlined label="Nome" :maxlength="160" :rules="[required, maxLength(160)]" />
            <BrazilianPhoneInput v-model="ticketForm.requester_phone" v-bind="serverErrorProps('requester_phone')" outlined label="Telefone" />
            <q-select v-if="!requesterOnly" v-model="ticketForm.department_id" v-bind="serverErrorProps('department_id')" outlined label="Setor" :options="departmentOptions" emit-value map-options clearable />
            <q-input v-else :model-value="departmentOptions[0]?.label || 'Seu setor'" outlined label="Setor" readonly />
            <q-input v-if="!requesterOnly" v-model="ticketForm.department_name" v-bind="serverErrorProps('department_name')" outlined label="Departamento" :maxlength="160" :rules="[maxLength(160)]" />
            <q-select v-model="ticketForm.type" v-bind="serverErrorProps('type')" outlined label="Tipo" :options="typeOptions" emit-value map-options />
            <q-select
              v-model="ticketForm.category_id"
              v-bind="serverErrorProps('category_id')"
              outlined
              label="Categoria"
              :options="filteredCategoryOptions"
              emit-value
              map-options
              clearable
              use-input
              input-debounce="200"
              @filter="filterCategoryOptions"
            />
            <q-select v-model="ticketForm.priority" v-bind="serverErrorProps('priority')" outlined label="Prioridade" :options="priorityOptions" emit-value map-options />
            <q-select v-model="ticketForm.impact" v-bind="serverErrorProps('impact')" outlined label="Impacto" :options="impactOptions" emit-value map-options clearable @update:model-value="updateCalculatedPriority" />
            <q-select v-model="ticketForm.urgency" v-bind="serverErrorProps('urgency')" outlined label="Urgencia" :options="urgencyOptions" emit-value map-options clearable @update:model-value="updateCalculatedPriority" />
            <q-select v-if="!requesterOnly" v-model="ticketForm.support_team_id" v-bind="serverErrorProps('support_team_id')" outlined label="Fila/equipe" :options="supportTeamOptions" emit-value map-options clearable />
            <q-select v-model="ticketForm.primary_asset_id" v-bind="serverErrorProps('primary_asset_id')" outlined label="Patrimonio principal" :options="assetOptions" emit-value map-options clearable />
            <q-select v-model="ticketForm.asset_ids" v-bind="serverErrorProps('asset_ids')" outlined label="Patrimonios relacionados" :options="assetOptions" emit-value map-options multiple clearable />
            <q-select v-if="!requesterOnly" v-model="ticketForm.assigned_to" v-bind="serverErrorProps('assigned_to')" outlined label="Tecnico" :options="technicianOptions" emit-value map-options clearable class="span-2" />
            <q-input v-model="ticketForm.description" v-bind="serverErrorProps('description')" outlined type="textarea" label="Descricao do problema" :maxlength="10000" counter :rules="[required, maxLength(10000)]" class="span-2" @update:model-value="loadKnowledgeSuggestions" />
            <div v-if="knowledgeSuggestions.length" class="span-2">
              <div class="text-subtitle2 q-mb-sm">Artigos sugeridos</div>
              <q-list bordered separator>
                <q-item v-for="article in knowledgeSuggestions" :key="article.id">
                  <q-item-section>
                    <q-item-label>{{ article.title }}</q-item-label>
                    <q-item-label caption>{{ article.summary || 'Base de conhecimento' }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="categoryDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ categoryForm.id ? 'Editar categoria' : 'Nova categoria' }}</div>
          <q-btn flat round dense icon="close" aria-label="Fechar categoria" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveCategory">
          <q-card-section class="form-grid">
            <q-input v-model="categoryForm.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="160" :rules="[required, maxLength(160)]" />
            <q-select v-model="categoryForm.parent_id" v-bind="serverErrorProps('parent_id')" outlined label="Categoria pai" :options="parentCategoryOptions" emit-value map-options clearable />
            <q-input v-model="categoryForm.description" v-bind="serverErrorProps('description')" outlined type="textarea" label="Descricao" :maxlength="2000" counter :rules="[maxLength(2000)]" class="span-2" />
            <q-input v-model="categoryForm.icon" v-bind="serverErrorProps('icon')" outlined label="Icone" :maxlength="80" :rules="[maxLength(80)]" />
            <q-input v-model="categoryForm.color" v-bind="serverErrorProps('color')" outlined label="Cor" :maxlength="40" :rules="[maxLength(40)]" />
            <q-input v-model.number="categoryForm.sort_order" v-bind="serverErrorProps('sort_order')" outlined type="number" label="Ordem" />
            <q-field borderless v-bind="serverErrorProps('active')"><template #control><q-toggle v-model="categoryForm.active" label="Ativo" /></template></q-field>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="slaDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ slaForm.id ? 'Editar regra de SLA' : 'Nova regra de SLA' }}</div>
          <q-btn flat round dense icon="close" aria-label="Fechar regra de SLA" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveSlaRule">
          <q-card-section class="form-grid">
            <q-input v-model="slaForm.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="160" :rules="[required, maxLength(160)]" />
            <q-select v-model="slaForm.calendar_id" v-bind="serverErrorProps('calendar_id')" outlined label="Calendario" :options="slaCalendarOptions" emit-value map-options clearable />
            <q-banner v-if="slaForm.is_fallback" dense class="bg-amber-1 text-dark">
              Regra fallback é usada somente quando nenhuma regra geral ou específica corresponder. Ela não possui escopo.
            </q-banner>
            <q-select v-model="slaForm.priority" v-bind="serverErrorProps('priority')" outlined label="Prioridade" :options="priorityOptions" emit-value map-options clearable :disable="slaForm.is_fallback" />
            <q-select v-model="slaForm.type" v-bind="serverErrorProps('type')" outlined label="Tipo" :options="typeOptions" emit-value map-options clearable :disable="slaForm.is_fallback" />
            <q-select v-model="slaForm.category_id" v-bind="serverErrorProps('category_id')" outlined label="Categoria" :options="categoryOptions" emit-value map-options clearable :disable="slaForm.is_fallback" />
            <q-select v-model="slaForm.department_id" v-bind="serverErrorProps('department_id')" outlined label="Setor" :options="departmentOptions" emit-value map-options clearable :disable="slaForm.is_fallback" />
            <q-input v-model.number="slaForm.first_response_minutes" v-bind="serverErrorProps('first_response_minutes')" outlined type="number" min="0" label="Primeira resposta (min)" :rules="[required]" />
            <q-input v-model.number="slaForm.start_minutes" v-bind="serverErrorProps('start_minutes')" outlined type="number" min="0" label="Inicio atendimento (min)" />
            <q-input v-model.number="slaForm.resolution_minutes" v-bind="serverErrorProps('resolution_minutes')" outlined type="number" min="1" label="Resolucao (min)" :rules="[required]" />
            <q-input v-model.number="slaForm.rule_priority" v-bind="serverErrorProps('rule_priority')" outlined type="number" label="Prioridade da regra" />
            <q-field borderless v-bind="serverErrorProps('is_fallback')"><template #control><q-toggle v-model="slaForm.is_fallback" label="Regra fallback" @update:model-value="onFallbackToggle" /></template></q-field>
            <q-field borderless v-bind="serverErrorProps('active')"><template #control><q-toggle v-model="slaForm.active" label="Ativa" /></template></q-field>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="checklistDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between"><div class="text-h6">{{ checklistForm.id ? 'Editar checklist' : 'Novo checklist' }}</div><q-btn flat round dense icon="close" aria-label="Fechar checklist" v-close-popup /></q-card-section>
        <q-separator />
        <q-form @submit="saveChecklistTemplate">
          <q-card-section class="form-grid">
            <q-input v-model="checklistForm.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="160" :rules="[required, maxLength(160)]" class="span-2" />
            <q-select v-model="checklistForm.category_id" v-bind="serverErrorProps('category_id')" outlined label="Categoria" :options="categoryOptions" emit-value map-options clearable />
            <q-select v-model="checklistForm.type" v-bind="serverErrorProps('type')" outlined label="Tipo" :options="typeOptions" emit-value map-options clearable />
            <div class="span-2"><div class="text-subtitle2 q-mb-sm">Itens</div><div v-for="(item, index) in checklistForm.items" :key="index" class="row q-col-gutter-sm q-mb-sm"><q-input v-model="item.label" v-bind="serverErrorProps(`items.${index}.label`)" outlined dense label="Item" :maxlength="255" class="col" :rules="[required, maxLength(255)]" /><q-field borderless v-bind="serverErrorProps(`items.${index}.required`)"><template #control><q-toggle v-model="item.required" label="Obrigatorio" /></template></q-field><q-btn flat round dense icon="delete" color="negative" :aria-label="`Remover item ${index + 1} do checklist`" @click="checklistForm.items.splice(index, 1)" /></div><q-btn flat icon="add" label="Adicionar item" @click="checklistForm.items.push({ label: '', required: false, sort_order: checklistForm.items.length })" /></div>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated /></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="responseTemplateDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between"><div class="text-h6">{{ responseTemplateForm.id ? 'Editar template' : 'Novo template' }}</div><q-btn flat round dense icon="close" aria-label="Fechar template de resposta" v-close-popup /></q-card-section>
        <q-separator />
        <q-form @submit="saveResponseTemplate">
          <q-card-section class="form-grid">
            <q-input v-model="responseTemplateForm.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="120" :rules="[required, maxLength(120)]" />
            <q-input v-model="responseTemplateForm.key" v-bind="serverErrorProps('key')" outlined label="Chave" hint="Ex.: retorno-padrao" :maxlength="120" :rules="[maxLength(120)]" />
            <q-select v-model="responseTemplateForm.scope" v-bind="serverErrorProps('scope')" outlined label="Escopo" :options="responseTemplateScopeOptions" emit-value map-options />
            <q-select v-if="responseTemplateForm.scope === 'equipe'" v-model="responseTemplateForm.support_team_id" v-bind="serverErrorProps('support_team_id')" outlined label="Equipe" :options="supportTeamOptions" emit-value map-options :rules="[required]" />
            <q-input v-model="responseTemplateForm.body" v-bind="serverErrorProps('body')" outlined type="textarea" label="Texto da resposta" :maxlength="4096" counter :rules="[required, maxLength(4096)]" class="span-2" />
            <q-field borderless v-bind="serverErrorProps('active')"><template #control><q-toggle v-model="responseTemplateForm.active" label="Ativo" /></template></q-field>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated /></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="automationDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between"><div class="text-h6">{{ automationForm.id ? 'Editar automação' : 'Nova automação' }}</div><q-btn flat round dense icon="close" aria-label="Fechar regra de automação" v-close-popup /></q-card-section>
        <q-separator />
        <q-form @submit="saveAutomationRule">
          <q-card-section class="form-grid">
            <q-input v-model="automationForm.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="120" :rules="[required, maxLength(120)]" class="span-2" />
            <q-select v-model="automationForm.event" v-bind="serverErrorProps('event')" outlined label="Evento" :options="automationEventOptions" emit-value map-options />
            <q-field borderless v-bind="serverErrorProps('active')"><template #control><q-toggle v-model="automationForm.active" label="Ativa" /></template></q-field>
            <q-input v-model="automationForm.conditionsText" v-bind="serverErrorProps('conditions', 'conditionsText')" outlined type="textarea" label="Condições (JSON)" hint="Exemplo: priority igual urgente" class="span-2" />
            <q-input v-model="automationForm.actionsText" v-bind="serverErrorProps('actions', 'actionsText')" outlined type="textarea" label="Ações (JSON)" hint="Informe uma lista JSON de ações" :rules="[required]" class="span-2" />
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated /></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="serviceCatalogDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between"><div class="text-h6">{{ serviceCatalogForm.id ? 'Editar serviço' : 'Novo serviço' }}</div><q-btn flat round dense icon="close" aria-label="Fechar serviço" v-close-popup /></q-card-section>
        <q-separator />
        <q-form @submit="saveServiceCatalog">
          <q-card-section class="form-grid">
            <q-input v-model="serviceCatalogForm.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="160" :rules="[required, maxLength(160)]" />
            <q-input v-model="serviceCatalogForm.key" v-bind="serverErrorProps('key')" outlined label="Chave" :maxlength="120" :rules="[required, maxLength(120)]" />
            <q-select v-model="serviceCatalogForm.category_id" v-bind="serverErrorProps('category_id')" outlined label="Categoria" :options="categoryOptions" emit-value map-options clearable />
            <q-select v-model="serviceCatalogForm.type" v-bind="serverErrorProps('type')" outlined label="Tipo" :options="typeOptions" emit-value map-options clearable />
            <q-select v-model="serviceCatalogForm.sla_rule_id" v-bind="serverErrorProps('sla_rule_id')" outlined label="Regra SLA" :options="slaRuleOptions" emit-value map-options clearable />
            <q-select v-model="serviceCatalogForm.checklist_template_id" v-bind="serverErrorProps('checklist_template_id')" outlined label="Checklist" :options="checklistTemplateOptions" emit-value map-options clearable />
            <q-input v-model="serviceCatalogForm.description" v-bind="serverErrorProps('description')" outlined type="textarea" label="Descrição" :maxlength="4000" counter :rules="[maxLength(4000)]" class="span-2" />
            <q-field borderless v-bind="serverErrorProps('requires_approval')"><template #control><q-toggle v-model="serviceCatalogForm.requires_approval" label="Exige aprovação" /></template></q-field>
            <q-input v-if="serviceCatalogForm.requires_approval" v-model="serviceCatalogForm.approval_role" v-bind="serverErrorProps('approval_role')" outlined label="Perfil aprovador" :maxlength="120" :rules="[maxLength(120)]" />
            <q-field borderless v-bind="serverErrorProps('active')"><template #control><q-toggle v-model="serviceCatalogForm.active" label="Ativo" /></template></q-field>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated /></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="detailDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card ticket-detail-dialog">
        <q-inner-loading :showing="detailLoading" color="primary"><q-spinner-gears size="42px" /></q-inner-loading>
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-h6">Chamado #{{ selectedTicket?.id }}</div>
            <div class="text-caption text-grey-7">{{ selectedTicket?.requester_name }} - {{ selectedTicket?.category_model?.name || selectedTicket?.category || 'sem categoria' }}</div>
          </div>
          <q-btn flat round dense icon="close" aria-label="Fechar detalhes do chamado" v-close-popup />
        </q-card-section>
        <q-separator />

        <q-card-section v-if="selectedTicket" class="ticket-detail-grid">
          <q-select v-model="statusForm.status" v-bind="serverErrorProps('status')" outlined dense label="Status" :options="selectedStatusOptions" emit-value map-options />
          <q-input v-model.number="statusForm.service_time_minutes" v-bind="serverErrorProps('service_time_minutes')" outlined dense type="number" label="Tempo em minutos" />
          <q-input
            v-if="statusForm.status === 'aguardando_usuario'"
            v-model="statusForm.reason"
            v-bind="serverErrorProps('reason')"
            outlined
            dense
            label="Motivo da pendencia"
            :maxlength="1000"
            :rules="[required, maxLength(1000)]"
          />
          <q-input
            v-if="statusForm.status === 'resolvido'"
            v-model="statusForm.resolution_summary"
            v-bind="serverErrorProps('resolution_summary')"
            outlined
            dense
            label="Resumo da solucao"
            :maxlength="1000"
            :rules="[required, maxLength(1000)]"
          />
          <q-select v-if="statusForm.status === 'resolvido'" v-model="statusForm.root_cause_id" v-bind="serverErrorProps('root_cause_id')" outlined dense label="Causa catalogada" :options="rootCauseOptions" emit-value map-options clearable />
          <q-input v-if="statusForm.status === 'resolvido'" v-model="statusForm.identified_cause" v-bind="serverErrorProps('identified_cause')" outlined dense label="Causa identificada" :maxlength="255" :rules="[maxLength(255)]" />
          <q-input v-if="statusForm.status === 'resolvido'" v-model="statusForm.root_cause" v-bind="serverErrorProps('root_cause')" outlined dense label="Causa raiz" :maxlength="1000" :rules="[maxLength(1000)]" />
          <q-input v-if="statusForm.status === 'resolvido'" v-model="statusForm.applied_solution" v-bind="serverErrorProps('applied_solution')" outlined dense label="Solucao aplicada" :maxlength="1000" :rules="[maxLength(1000)]" />
          <q-input v-if="statusForm.status === 'resolvido'" v-model="statusForm.executed_procedure" v-bind="serverErrorProps('executed_procedure')" outlined dense label="Procedimento executado" :maxlength="1000" :rules="[maxLength(1000)]" />
          <q-input v-if="statusForm.status === 'resolvido'" v-model="statusForm.replaced_part" v-bind="serverErrorProps('replaced_part')" outlined dense label="Peca substituida" :maxlength="255" :rules="[maxLength(255)]" />
          <q-field v-if="statusForm.status === 'resolvido'" borderless v-bind="serverErrorProps('requires_follow_up')"><template #control><q-toggle v-model="statusForm.requires_follow_up" label="Necessita acompanhamento" /></template></q-field>
          <q-field v-if="statusForm.status === 'resolvido'" borderless v-bind="serverErrorProps('recurring_problem')"><template #control><q-toggle v-model="statusForm.recurring_problem" label="Problema recorrente" /></template></q-field>
          <q-input v-if="statusForm.status === 'resolvido'" v-model="statusForm.internal_technical_note" v-bind="serverErrorProps('internal_technical_note')" outlined dense label="Observacao tecnica interna" :maxlength="2000" :rules="[maxLength(2000)]" />
          <q-btn v-if="canManageTicket" color="primary" icon="published_with_changes" label="Atualizar status" unelevated :disable="!canSubmitStatus" @click="changeStatus" />
          <q-btn v-if="canEdit && selectedTicket?.resolution_summary" color="secondary" icon="menu_book" label="Criar artigo da solução" outline @click="createKnowledgeArticle" />
          <q-btn v-if="canEdit && selectedTicket && !['encerrado', 'cancelado'].includes(selectedTicket.status)" color="negative" icon="cancel" label="Cancelar chamado" outline @click="cancelSelectedTicket" />
          <q-btn v-if="canEdit && selectedTicket?.is_major_incident" color="warning" icon="sync_alt" label="Atualizar incidentes filhos" outline @click="reflectIncident" />
        </q-card-section>

        <q-separator />

        <q-card-section v-if="selectedTicket" class="detail-grid">
          <div>
            <span>Tipo</span>
            <strong>
              <q-icon :name="typeMeta(selectedTicket.type).icon" :color="typeMeta(selectedTicket.type).color" />
              {{ typeMeta(selectedTicket.type).label }}
            </strong>
            <p>{{ selectedTicket.category_model?.name || selectedTicket.category || 'Sem categoria.' }}</p>
          </div>
          <div>
            <span>Responsaveis</span>
            <strong>{{ selectedTicket.assignee?.name || 'Sem tecnico' }}</strong>
            <p>{{ selectedTicket.support_team?.name || 'Sem fila' }} - aberto por {{ selectedTicket.opened_by?.name || '-' }}</p>
          </div>
          <div>
            <span>Causa raiz</span>
            <strong>{{ selectedTicket.root_cause_catalog?.name || selectedTicket.identified_cause || 'Nao informada' }}</strong>
            <p>{{ selectedTicket.root_cause || selectedTicket.applied_solution || 'Sem detalhamento tecnico.' }}</p>
          </div>
          <div>
            <span>Prazos</span>
            <strong>SLA {{ formatDateTime(selectedTicket.sla_due_at) }}</strong>
            <p>{{ selectedTicket.sla_rule?.name || 'Regra fallback' }} - 1a resposta {{ formatDateTime(selectedTicket.first_response_due_at) }}</p>
          </div>
          <div>
            <span>Patrimonio</span>
            <strong>{{ selectedTicket.primary_asset?.asset_number || 'Sem patrimonio' }}</strong>
            <p>{{ selectedTicket.primary_asset ? `${selectedTicket.primary_asset.description} ${selectedTicket.primary_asset.brand || ''} ${selectedTicket.primary_asset.model || ''}` : 'Nenhum equipamento vinculado.' }}</p>
          </div>
          <div>
            <span>Solucao</span>
            <strong>{{ selectedTicket.resolver?.name || 'Nao resolvido' }}</strong>
            <p>{{ selectedTicket.resolution_summary || 'Sem resumo de solucao.' }}</p>
          </div>
          <div>
            <span>Reaberturas</span>
            <strong>{{ selectedTicket.reopen_count || 0 }}</strong>
            <p>{{ selectedTicket.pending_reason || 'Sem pendencia atual.' }}</p>
          </div>
        </q-card-section>

        <q-separator />
        <q-card-section v-if="selectedTicket" class="detail-grid">
          <div>
            <span>Observadores</span>
            <div v-if="canEdit" class="row q-gutter-xs q-mt-sm">
              <q-select v-model="followerUserId" v-bind="serverErrorProps('user_id')" dense outlined :options="followerOptions" emit-value map-options label="Adicionar" class="col" />
              <q-btn round dense color="primary" icon="person_add" aria-label="Adicionar observador" :disable="!followerUserId" @click="addFollower" />
            </div>
            <div class="q-mt-sm">
              <q-chip v-for="follower in selectedTicket.followers || []" :key="follower.id" dense icon="person">{{ follower.name }}<q-btn v-if="canEdit" flat round dense size="xs" icon="close" :aria-label="`Remover observador ${follower.name}`" @click="removeFollower(follower)" /></q-chip>
              <span v-if="!(selectedTicket.followers || []).length" class="text-caption text-grey-7">Nenhum observador.</span>
            </div>
          </div>
          <div>
            <span>Checklist</span>
            <q-list v-if="selectedTicket.checklist_items?.length" dense bordered class="q-mt-sm">
              <q-item v-for="item in selectedTicket.checklist_items" :key="item.id">
                <q-item-section>
                  <q-item-label>{{ item.label }}<q-badge v-if="item.required" color="negative" outline class="q-ml-xs">obrigatorio</q-badge></q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-toggle :model-value="item.status === 'concluido'" :disable="!canEdit" @update:model-value="value => updateChecklistItem(item, value)" />
                </q-item-section>
              </q-item>
            </q-list>
            <span v-else class="text-caption text-grey-7">Nenhum checklist.</span>
          </div>
          <div>
            <span>Tarefas</span>
            <div v-if="canEdit" class="row q-col-gutter-xs q-mt-sm">
              <q-input v-model="taskForm.title" v-bind="serverErrorProps('title')" dense outlined label="Nova tarefa" :maxlength="255" :rules="[maxLength(255)]" class="col" />
              <q-btn round dense color="primary" icon="add" aria-label="Adicionar tarefa" :disable="!taskForm.title" @click="createTask" />
            </div>
            <q-list v-if="selectedTicket.tasks?.length" dense bordered class="q-mt-sm">
              <q-item v-for="task in selectedTicket.tasks" :key="task.id">
                <q-item-section>
                  <q-item-label>{{ task.title }}<q-badge v-if="task.required" color="negative" outline class="q-ml-xs">obrigatoria</q-badge></q-item-label>
                  <q-item-label caption>{{ task.assigned_to?.name || 'Sem responsavel' }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-select v-if="canEdit" :model-value="task.status" dense outlined :options="taskStatusOptions" emit-value map-options @update:model-value="value => updateTaskStatus(task, value)" />
                  <q-badge v-else outline>{{ task.status }}</q-badge>
                </q-item-section>
              </q-item>
            </q-list>
            <span v-else class="text-caption text-grey-7">Nenhuma tarefa.</span>
          </div>
          <div>
            <span>Chamados relacionados</span>
            <div v-if="canEdit" class="row q-col-gutter-xs q-mt-sm">
              <q-input v-model.number="relationshipForm.related_ticket_id" v-bind="serverErrorProps('related_ticket_id')" dense outlined type="number" label="# chamado" class="col" />
              <q-select v-model="relationshipForm.type" v-bind="serverErrorProps('type')" dense outlined :options="relationshipTypes" emit-value map-options label="Tipo" class="col" />
              <q-btn round dense color="primary" icon="link" aria-label="Relacionar chamado" :disable="!relationshipForm.related_ticket_id" @click="createRelationship" />
              <q-btn round dense color="negative" icon="merge_type" aria-label="Mesclar chamado duplicado" :disable="!relationshipForm.related_ticket_id" @click="mergeDuplicate" />
            </div>
            <q-list v-if="selectedTicket.relationships?.length" dense bordered class="q-mt-sm">
              <q-item v-for="relationship in selectedTicket.relationships" :key="relationship.id">
                <q-item-section><q-item-label>#{{ relationship.related_ticket_id }}</q-item-label><q-item-label caption>{{ relationship.type }}</q-item-label></q-item-section>
              </q-item>
            </q-list>
            <span v-else class="text-caption text-grey-7">Nenhum relacionamento.</span>
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section v-if="selectedTicket && canEvaluateTicket" class="attachment-panel">
          <div class="text-subtitle2 q-mb-sm">Avaliacao do atendimento</div>
          <q-form class="row q-col-gutter-sm items-start" @submit="saveEvaluation">
            <div class="col-auto">
              <q-field borderless v-bind="serverErrorProps('rating')"><template #control><q-rating v-model="evaluationForm.rating" size="28px" color="warning" :max="5" /></template></q-field>
            </div>
            <div class="col">
              <q-input v-model="evaluationForm.comment" v-bind="serverErrorProps('comment')" outlined dense label="Comentario" :maxlength="1000" :rules="[maxLength(1000)]" />
            </div>
            <div class="col-auto">
              <q-btn type="submit" color="primary" icon="star" label="Avaliar" unelevated />
            </div>
          </q-form>
        </q-card-section>

        <q-card-section v-else-if="selectedTicket?.evaluation" class="attachment-panel">
          <div class="text-subtitle2 q-mb-sm">Avaliacao do atendimento</div>
          <q-rating :model-value="selectedTicket.evaluation.rating" readonly size="22px" color="warning" />
          <div class="text-caption text-grey-7">{{ selectedTicket.evaluation.comment || 'Sem comentario.' }}</div>
        </q-card-section>

        <q-separator />

        <q-card-section v-if="selectedTicket" class="attachment-panel">
          <div class="text-subtitle2 q-mb-sm">Historico de status</div>
          <q-list v-if="selectedTicket.status_histories?.length" bordered separator class="attachment-list">
            <q-item v-for="history in selectedTicket.status_histories" :key="history.id">
              <q-item-section avatar>
                <q-icon :name="statusIcon(history.to_status)" :color="statusColor(history.to_status)" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ statusLabel(history.from_status || 'criacao') }} para {{ statusLabel(history.to_status) }}</q-item-label>
                <q-item-label caption>{{ history.changed_by?.name || 'sem usuario' }} - {{ formatDateTime(history.created_at) }}</q-item-label>
                <q-item-label v-if="history.reason" caption>{{ history.reason }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <div v-else class="empty-note">Nenhuma alteracao de status registrada.</div>
        </q-card-section>

        <q-card-section v-if="selectedTicket" class="attachment-panel">
          <div class="text-subtitle2 q-mb-sm">Timeline completa</div>
          <q-timeline v-if="timeline.length" color="primary" layout="dense">
            <q-timeline-entry v-for="(event, index) in timeline" :key="`${event.type}-${event.data?.id || index}`" :title="timelineTitle(event)" :subtitle="formatDateTime(event.at)">
              <div class="text-caption">{{ timelineDescription(event) }}</div>
            </q-timeline-entry>
          </q-timeline>
          <div v-else class="empty-note">Nenhum evento adicional registrado.</div>
        </q-card-section>

        <q-separator />

        <q-card-section v-if="selectedTicket" class="attachment-panel">
          <div class="attachment-header">
            <div>
              <div class="text-subtitle2">Anexos</div>
              <div class="text-caption text-grey-7">{{ attachments.length }} arquivo(s) vinculados ao chamado</div>
            </div>
            <q-file
              v-if="canManageTicket"
              v-model="attachmentFile"
              v-bind="serverErrorProps('file')"
              dense
              outlined
              clearable
              label="Selecionar arquivo"
              class="attachment-picker"
            >
              <template #prepend><q-icon name="attach_file" /></template>
            </q-file>
            <q-btn
              v-if="canManageTicket"
              color="primary"
              icon="upload"
              round
              unelevated
              :disable="!attachmentFile"
              :loading="uploadingAttachment"
              aria-label="Enviar anexo do chamado"
              @click="uploadTicketAttachment"
            >
              <q-tooltip>Enviar anexo</q-tooltip>
            </q-btn>
          </div>

          <q-list v-if="attachments.length" bordered separator class="attachment-list">
            <q-item v-for="attachment in attachments" :key="attachment.id">
              <q-item-section avatar>
                <q-icon name="description" color="primary" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ attachment.original_name }}</q-item-label>
                <q-item-label caption>
                  {{ formatFileSize(attachment.size) }} - {{ attachment.uploaded_by || 'sem usuario' }} - {{ formatDateTime(attachment.created_at) }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <div class="row no-wrap q-gutter-xs">
                  <q-btn flat round dense icon="download" aria-label="Baixar anexo do chamado" @click="downloadAttachment(attachment)">
                    <q-tooltip>Baixar anexo</q-tooltip>
                  </q-btn>
                  <q-btn v-if="canManageTicket" flat round dense icon="delete" color="negative" aria-label="Remover anexo do chamado" @click="removeTicketAttachment(attachment)">
                    <q-tooltip>Remover anexo</q-tooltip>
                  </q-btn>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
          <div v-else class="empty-note">Nenhum anexo registrado.</div>
        </q-card-section>

        <q-separator />

        <q-card-section class="message-list">
          <q-chat-message
            v-for="message in selectedTicket?.messages || []"
            :key="message.id"
            :name="message.user?.name || message.direction"
            :text="[message.message]"
            :sent="message.direction !== 'inbound'"
            :stamp="formatDateTime(message.created_at)"
          />
        </q-card-section>

        <q-separator />
        <q-card-section>
          <q-form class="row q-col-gutter-sm" @submit="sendMessage">
            <div class="col">
              <q-select v-if="!requesterOnly && responseTemplates.length" v-model="responseTemplateId" data-local-only dense outlined clearable label="Usar template" :options="responseTemplateOptions" emit-value map-options @update:model-value="applyResponseTemplate" />
              <q-input v-model="messageForm.message" v-bind="serverErrorProps('message')" outlined dense label="Mensagem" :maxlength="10000" counter :rules="[required, maxLength(10000)]" />
              <q-field v-if="canEdit" borderless v-bind="serverErrorProps('internal')"><template #control><q-toggle v-model="messageForm.internal" dense label="Nota interna" /></template></q-field>
            </div>
            <div class="col-auto">
              <q-btn type="submit" color="primary" icon="send" round unelevated aria-label="Enviar mensagem">
                <q-tooltip>Enviar mensagem</q-tooltip>
              </q-btn>
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ItKanbanBoard from '@/components/ItKanbanBoard.vue'
import ItSlaCalendars from '@/components/ItSlaCalendars.vue'
import BrazilianPhoneInput from '@/components/BrazilianPhoneInput.vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { apiDownload, apiFetch, apiUpload } from '@/services/api'
import { clearServerValidationErrors, serverErrorProps } from '@/services/server-validation'
import { useAuthStore } from '@/stores/auth-store'
import { formatDateTime } from '@/composables/useDateFormat'
import { maxLength } from '@/utils/form-validation'

const $q = useQuasar()
const auth = useAuthStore()
const route = useRoute()
const tab = ref('tickets')
const loading = ref(false)
const tickets = ref([])
const ticketPagination = ref({ page: 1, rowsPerPage: 15, rowsNumber: 0, sortBy: 'id', descending: true })
const kanbanPagination = ref({ page: 1, rowsPerPage: 25, rowsNumber: 0, lastPage: 1, from: null, to: null })
const categories = ref([])
const knowledgeArticles = ref([])
const supportTeams = ref([])
const checklistTemplates = ref([])
const responseTemplates = ref([])
const automationRules = ref([])
const serviceCatalog = ref([])
const approvals = ref([])
const slaRules = ref([])
const slaCalendars = ref([])
const slaTimezone = ref('UTC')
const categorySearch = ref('')
const options = ref({ departments: [], technicians: [], categories: [], types: [] })
const summary = ref({})
const ticketDialog = ref(false)
const categoryDialog = ref(false)
const slaDialog = ref(false)
const detailDialog = ref(false)
const checklistDialog = ref(false)
const responseTemplateDialog = ref(false)
const automationDialog = ref(false)
const serviceCatalogDialog = ref(false)
const selectedTicket = ref(null)
const attachments = ref([])
const timeline = ref([])
const attachmentFile = ref(null)
const uploadingAttachment = ref(false)
const detailLoading = ref(false)
const filteredCategoryOptions = ref([])
const selectedTickets = ref([])
const bulkActionForm = reactive({ action: 'assign', value: null })
const dashboardLoading = ref(false)
const dashboard = ref({ indicators: {}, charts: {}, technician: {} })
const recurrences = ref({})

const required = value => value !== null && value !== undefined && value !== '' || 'Campo obrigatorio'

const statusOptions = [
  { label: 'Aberto', value: 'aberto' },
  { label: 'Triagem', value: 'triagem' },
  { label: 'Em atendimento', value: 'em_atendimento' },
  { label: 'Aguardando usuario', value: 'aguardando_usuario' },
  { label: 'Resolvido', value: 'resolvido' },
  { label: 'Encerrado', value: 'encerrado' },
  { label: 'Cancelado', value: 'cancelado' }
]
const bulkActionOptions = [
  { label: 'Atribuir técnico', value: 'assign' },
  { label: 'Alterar prioridade', value: 'priority' },
  { label: 'Alterar categoria', value: 'category' },
  { label: 'Alterar equipe', value: 'team' },
  { label: 'Encerrar chamados', value: 'close' }
]
const dashboardCards = [
  { key: 'open', label: 'Em aberto', icon: 'forum', color: 'primary' },
  { key: 'triage', label: 'Em triagem', icon: 'manage_search', color: 'warning' },
  { key: 'in_progress', label: 'Em atendimento', icon: 'support_agent', color: 'info' },
  { key: 'waiting_user', label: 'Aguardando usuário', icon: 'notifications', color: 'orange' },
  { key: 'sla_overdue', label: 'SLA vencido', icon: 'timer', color: 'negative' },
  { key: 'sla_compliance_percent', label: 'Cumprimento de SLA', icon: 'task_alt', color: 'positive', suffix: '%' },
  { key: 'mtta_minutes', label: 'MTTA (min)', icon: 'schedule', color: 'secondary' },
  { key: 'mttr_minutes', label: 'MTTR (min)', icon: 'speed', color: 'deep-orange' },
  { key: 'reopened', label: 'Reabertos', icon: 'refresh', color: 'warning' },
  { key: 'unassigned', label: 'Sem técnico', icon: 'person', color: 'negative' }
]
const recommendedColumns = [
  { name: 'id', label: '#', field: 'id', align: 'right' },
  { name: 'description', label: 'Descrição', field: 'description', align: 'left' },
  { name: 'priority', label: 'Prioridade', field: row => labelFor(row.priority), align: 'left' },
  { name: 'status', label: 'Status', field: row => statusLabel(row.status), align: 'left' },
  { name: 'sla_due_at', label: 'SLA', field: row => formatDateTime(row.sla_due_at), align: 'left' }
]
const recurrenceColumns = [
  { name: 'dimension', label: 'Dimensão', field: 'dimension', align: 'left' },
  { name: 'key', label: 'Identificador', field: 'key', align: 'left' },
  { name: 'total', label: 'Ocorrências', field: 'total', align: 'right' }
]

const priorityOptions = [
  { label: 'Baixa', value: 'baixa' },
  { label: 'Media', value: 'media' },
  { label: 'Alta', value: 'alta' },
  { label: 'Urgente', value: 'urgente' }
]

const impactOptions = [
  { label: 'Baixo', value: 'baixo' },
  { label: 'Medio', value: 'medio' },
  { label: 'Alto', value: 'alto' }
]

const urgencyOptions = [
  { label: 'Baixa', value: 'baixa' },
  { label: 'Media', value: 'media' },
  { label: 'Alta', value: 'alta' }
]

const sourceOptions = [
  { label: 'Manual', value: 'manual' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'Preventiva', value: 'preventiva' }
]

const slaOptions = [
  { label: 'Vencidos', value: 'overdue' },
  { label: 'Vencem hoje', value: 'due_today' }
]

const ticketForm = reactive(defaultTicket())
const categoryForm = reactive(defaultCategory())
const slaForm = reactive(defaultSlaRule())
const checklistForm = reactive({ id: null, name: '', category_id: null, type: null, items: [] })
const followerUserId = ref(null)
const taskForm = reactive({ title: '' })
const relationshipForm = reactive({ related_ticket_id: null, type: 'relacionado_a' })
const relationshipTypes = [
  { label: 'Relacionado', value: 'relacionado_a' },
  { label: 'Duplicado', value: 'duplicado_de' },
  { label: 'Bloqueado por', value: 'bloqueado_por' },
  { label: 'Causado por', value: 'causado_por' },
  { label: 'Problema pai', value: 'problema_pai' }
]
const statusForm = reactive({
  status: 'triagem',
  service_time_minutes: 0,
  reason: '',
  resolution_summary: '',
  root_cause_id: null,
  identified_cause: '',
  root_cause: '',
  applied_solution: '',
  executed_procedure: '',
  replaced_part: '',
  requires_follow_up: false,
  recurring_problem: false,
  internal_technical_note: ''
})
const messageForm = reactive({ message: '', internal: false })
const responseTemplateForm = reactive({ id: null, name: '', key: '', body: '', scope: 'global', support_team_id: null, active: true })
const responseTemplateId = ref(null)
const automationForm = reactive({ id: null, name: '', event: 'ticket_created', conditionsText: '{}', actionsText: '[]', active: true })
const serviceCatalogForm = reactive({ id: null, name: '', key: '', description: '', category_id: null, type: null, sla_rule_id: null, checklist_template_id: null, requires_approval: false, approval_role: '', active: true })
const evaluationForm = reactive({ rating: 5, comment: '' })
const taskStatusOptions = [
  { label: 'Pendente', value: 'pendente' },
  { label: 'Em andamento', value: 'em_andamento' },
  { label: 'Concluida', value: 'concluida' },
  { label: 'Cancelada', value: 'cancelada' }
]
const knowledgeSuggestions = ref([])
const filters = reactive({ search: '', status: null, type: null, priority: null, category_id: null, department_id: null, assigned_to: null, source: null, sla: null, date_from: '', date_to: '' })

const ticketColumns = [
  { name: 'id', label: '#', field: 'id', align: 'left', sortable: true },
  { name: 'requester_name', label: 'Solicitante', field: 'requester_name', align: 'left' },
  { name: 'type', label: 'Tipo', field: 'type', align: 'left' },
  { name: 'category', label: 'Categoria', field: row => row.category_model?.name || row.category || '-', align: 'left' },
  { name: 'priority', label: 'Prioridade', field: 'priority', align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'left' },
  { name: 'assignee', label: 'Tecnico', field: row => row.assignee?.name || '-', align: 'left' },
  { name: 'sla_due_at', label: 'SLA', field: row => formatDateTime(row.sla_due_at), align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const categoryColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
  { name: 'parent', label: 'Categoria pai', field: row => row.parent?.name || '-', align: 'left' },
  { name: 'children_count', label: 'Subcategorias', field: 'children_count', align: 'right' },
  { name: 'tickets_count', label: 'Chamados', field: 'tickets_count', align: 'right' },
  { name: 'sort_order', label: 'Ordem', field: 'sort_order', align: 'right' },
  { name: 'active', label: 'Status', field: 'active', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]
const checklistColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left' },
  { name: 'category', label: 'Categoria', field: row => row.category?.name || 'Todas', align: 'left' },
  { name: 'type', label: 'Tipo', field: 'type', align: 'left' },
  { name: 'items', label: 'Itens', field: 'items', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const knowledgeColumns = [
  { name: 'title', label: 'Titulo', field: 'title', align: 'left', sortable: true },
  { name: 'category', label: 'Categoria', field: row => row.category?.name || '-', align: 'left' },
  { name: 'views_count', label: 'Visualizacoes', field: 'views_count', align: 'right' },
  { name: 'public', label: 'Publicado', field: 'public', align: 'center' }
]
const teamColumns = [
  { name: 'name', label: 'Equipe', field: 'name', align: 'left', sortable: true },
  { name: 'technicians', label: 'Tecnicos', field: 'technicians', align: 'left' },
  { name: 'categories', label: 'Categorias', field: 'categories', align: 'left' },
  { name: 'active', label: 'Status', field: row => row.active ? 'Ativa' : 'Inativa', align: 'left' }
]
const responseTemplateColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
  { name: 'scope', label: 'Escopo', field: 'scope', align: 'left' },
  { name: 'body', label: 'Texto', field: 'body', align: 'left' },
  { name: 'active', label: 'Status', field: 'active', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]
const automationColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left' },
  { name: 'event', label: 'Evento', field: 'event', align: 'left' },
  { name: 'conditions', label: 'Condições', field: 'conditions', align: 'left' },
  { name: 'actions', label: 'Ações', field: 'actions', align: 'left' },
  { name: 'active', label: 'Ativa', field: 'active', align: 'center' },
  { name: 'edit', label: '', field: 'edit', align: 'right' }
]
const serviceCatalogColumns = [
  { name: 'name', label: 'Serviço', field: 'name', align: 'left' },
  { name: 'key', label: 'Chave', field: 'key', align: 'left' },
  { name: 'category', label: 'Categoria', field: 'category', align: 'left' },
  { name: 'type', label: 'Tipo', field: row => labelFor(row.type), align: 'left' },
  { name: 'requires_approval', label: 'Aprovação', field: row => row.requires_approval ? 'Sim' : 'Não', align: 'center' },
  { name: 'active', label: 'Status', field: 'active', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]
const approvalColumns = [
  { name: 'ticket', label: 'Chamado', field: 'ticket', align: 'left' },
  { name: 'requested_by', label: 'Solicitante', field: row => row.requester?.name || '-', align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'left' },
  { name: 'decided_at', label: 'Decidido em', field: row => formatDateTime(row.decided_at), align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]
const responseTemplateScopeOptions = [
  { label: 'Global', value: 'global' },
  { label: 'Pessoal', value: 'pessoal' },
  { label: 'Equipe', value: 'equipe' }
]
const automationEventOptions = [
  { label: 'Chamado aberto', value: 'ticket_created' },
  { label: 'Mudança de status', value: 'status_changed' },
  { label: 'Mensagem criada', value: 'message_created' }
]

const slaColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
  { name: 'scope', label: 'Abrangencia', field: 'scope', align: 'left' },
  { name: 'times', label: 'Prazos', field: 'times', align: 'left' },
  { name: 'rule_priority', label: 'Ordem', field: 'rule_priority', align: 'right', sortable: true },
  { name: 'active', label: 'Status', field: 'active', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]
const recurrenceRows = computed(() => Object.entries(recurrences.value || {}).flatMap(([dimension, rows]) => (rows || []).map(row => ({ ...row, id: `${dimension}-${row.key}`, dimension: labelFor(dimension.replace('by_', '')) }))))

const departmentOptions = computed(() => options.value.departments.map(item => ({ label: item.name, value: item.id })))
const technicianOptions = computed(() => options.value.technicians.map(item => ({ label: item.name, value: item.id })))
const followerOptions = computed(() => (options.value.technicians || []).filter(item => !(selectedTicket.value?.followers || []).some(follower => follower.id === item.id)).map(item => ({ label: item.name, value: item.id })))
const categoryOptions = computed(() => buildCategoryOptions(options.value.categories || []))
const typeOptions = computed(() => (options.value.types || []).map(item => ({ label: item.label, value: item.value })))
const slaCalendarOptions = computed(() => slaCalendars.value.map(item => ({ label: item.name + (item.active ? '' : ' (inativo)'), value: item.id, disable: !item.active })))
const assetOptions = computed(() => (options.value.assets || []).map(item => ({
  label: `${item.asset_number} - ${item.description}${item.model ? ` (${item.model})` : ''}`,
  value: item.id
})))
const supportTeamOptions = computed(() => (options.value.support_teams || []).map(item => ({ label: item.name, value: item.id })))
const responseTemplateOptions = computed(() => responseTemplates.value.map(item => ({ label: item.name, value: item.id })))
const slaRuleOptions = computed(() => slaRules.value.map(item => ({ label: item.name, value: item.id })))
const checklistTemplateOptions = computed(() => checklistTemplates.value.map(item => ({ label: item.name, value: item.id })))
const bulkValueOptions = computed(() => {
  if (bulkActionForm.action === 'assign') return technicianOptions.value
  if (bulkActionForm.action === 'category') return categoryOptions.value
  if (bulkActionForm.action === 'team') return supportTeamOptions.value
  if (bulkActionForm.action === 'priority') return priorityOptions
  return []
})
const rootCauseOptions = computed(() => (options.value.root_causes || []).map(item => ({ label: item.name, value: item.id })))
const parentCategoryOptions = computed(() => buildCategoryOptions(categories.value.filter(item => item.id !== categoryForm.id)))
const requesterOnly = computed(() => Boolean(route.meta.requesterOnly))
const canCreate = computed(() => auth.can('informatica.cadastrar'))
const canEdit = computed(() => auth.can('informatica.editar'))
const hasActiveFilters = computed(() => Object.entries(filters).some(([key, value]) => key !== 'date_from' && key !== 'date_to' ? value !== null && value !== '' : Boolean(value)))
const canManageTicket = computed(() => canEdit.value || requesterOnly.value)
const canViewCategories = computed(() => auth.can('informatica.categorias.visualizar'))
const canCreateCategory = computed(() => auth.can('informatica.categorias.cadastrar'))
const canEditCategory = computed(() => auth.can('informatica.categorias.editar'))
const canDeleteCategory = computed(() => auth.can('informatica.categorias.excluir'))
const canManageSla = computed(() => !requesterOnly.value && canEdit.value)
const selectedStatusOptions = computed(() => {
  if (!selectedTicket.value) return statusOptions

  const transitions = requesterOnly.value && !canEdit.value
    ? (selectedTicket.value.status === 'resolvido' ? ['em_atendimento'] : [])
    : (selectedTicket.value.available_transitions || [])
  const allowed = new Set([selectedTicket.value.status, ...transitions])

  return statusOptions.filter(option => allowed.has(option.value))
})
const canSubmitStatus = computed(() => {
  if (!selectedTicket.value || statusForm.status === selectedTicket.value.status) return false
  if (!(selectedTicket.value.available_transitions || []).includes(statusForm.status)) return false
  if (requesterOnly.value && !canEdit.value && !(selectedTicket.value.status === 'resolvido' && statusForm.status === 'em_atendimento')) return false
  if (statusForm.status === 'aguardando_usuario' && !String(statusForm.reason || '').trim()) return false
  if (statusForm.status === 'resolvido' && !String(statusForm.resolution_summary || '').trim()) return false

  return true
})
const kanbanPageSizeOptions = [
  { label: '15', value: 15 },
  { label: '25', value: 25 },
  { label: '50', value: 50 },
  { label: '100', value: 100 }
]
const kanbanPageCount = computed(() => Math.max(1, kanbanPagination.value.lastPage || 1))
const canEvaluateTicket = computed(() => {
  if (!selectedTicket.value) return false
  if (selectedTicket.value.evaluation) return false
  return ['resolvido', 'encerrado'].includes(selectedTicket.value.status)
})

onMounted(loadAll)

async function loadAll() {
  loading.value = true

  try {
    const activePagination = tab.value === 'kanban' ? kanbanPagination.value : ticketPagination.value
    const [ticketData, optionData, summaryData, categoryData, slaRuleData, slaCalendarData] = await Promise.all([
      apiFetch(`/it/tickets${queryString({ ...filters, page: activePagination.page, per_page: activePagination.rowsPerPage, mine: requesterOnly.value ? 1 : '', kanban: tab.value === 'kanban' ? 1 : '' })}`),
      apiFetch('/it/options'),
      apiFetch(`/it/tickets/summary${queryString({ mine: requesterOnly.value ? 1 : '' })}`),
      canViewCategories.value ? apiFetch(`/it/categories${queryString({ search: categorySearch.value })}`) : Promise.resolve({ data: [] }),
      canManageSla.value ? apiFetch('/it/sla-rules') : Promise.resolve({ data: { data: [] } }),
      canManageSla.value ? apiFetch('/it/sla-calendars') : Promise.resolve({ data: [] })
    ])

    tickets.value = ticketData.data || []
    if (tab.value === 'kanban' && ticketData.meta) {
      kanbanPagination.value = {
        ...kanbanPagination.value,
        page: ticketData.meta.current_page,
        rowsPerPage: ticketData.meta.per_page,
        rowsNumber: ticketData.meta.total,
        lastPage: ticketData.meta.last_page,
        from: ticketData.meta.from,
        to: ticketData.meta.to
      }
    } else if (ticketData.current_page) {
      ticketPagination.value = { ...ticketPagination.value, page: ticketData.current_page, rowsPerPage: ticketData.per_page, rowsNumber: ticketData.total }
    }
    options.value = optionData
    filteredCategoryOptions.value = categoryOptions.value
    summary.value = summaryData
    categories.value = categoryData.data || []
    slaRules.value = slaRuleData.data?.data || slaRuleData.data || []
    slaCalendars.value = slaCalendarData.data || []
    slaTimezone.value = slaCalendarData.timezone || 'UTC'
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

async function onTicketRequest({ pagination }) {
  ticketPagination.value = { ...ticketPagination.value, ...pagination }
  await loadAll()
}

async function loadTicketPage() {
  loading.value = true
  try {
    const activePagination = tab.value === 'kanban' ? kanbanPagination.value : ticketPagination.value
    const ticketData = await apiFetch(`/it/tickets${queryString({ ...filters, page: activePagination.page, per_page: activePagination.rowsPerPage, mine: requesterOnly.value ? 1 : '', kanban: tab.value === 'kanban' ? 1 : '' })}`)
    tickets.value = ticketData.data || []
    if (tab.value === 'kanban' && ticketData.meta) {
      kanbanPagination.value = {
        ...kanbanPagination.value,
        page: ticketData.meta.current_page,
        rowsPerPage: ticketData.meta.per_page,
        rowsNumber: ticketData.meta.total,
        lastPage: ticketData.meta.last_page,
        from: ticketData.meta.from,
        to: ticketData.meta.to
      }
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

async function changeKanbanPage(page) {
  kanbanPagination.value.page = page
  await loadTicketPage()
}

async function changeKanbanPageSize(rowsPerPage) {
  kanbanPagination.value = { ...kanbanPagination.value, page: 1, rowsPerPage }
  await loadTicketPage()
}

async function applyKanbanFilters() {
  kanbanPagination.value.page = 1
  await loadTicketPage()
}

async function applyBulkAction() {
  if (!selectedTickets.value.length) return
  const execute = async () => {
    try {
      await apiFetch('/it/tickets/bulk-action', {
        method: 'POST',
        body: JSON.stringify({ ticket_ids: selectedTickets.value.map(ticket => ticket.id), action: bulkActionForm.action, value: bulkActionForm.value })
      })
      selectedTickets.value = []
      bulkActionForm.value = null
      await loadAll()
      $q.notify({ type: 'positive', message: 'Ação aplicada aos chamados selecionados.' })
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  }

  if (bulkActionForm.action === 'close') {
    $q.dialog({
      title: 'Confirmar encerramento em massa',
      message: `Os ${selectedTickets.value.length} chamados selecionados serão encerrados e registrados no histórico. Continuar?`,
      cancel: true,
      persistent: true
    }).onOk(execute)
    return
  }

  await execute()
}

async function changeTab(value) {
  if (value === 'dashboard') await loadDashboard()
  if (value === 'kanban' || tab.value === 'tickets') {
    await loadAll()
  }
  if (value === 'knowledge') await loadKnowledge()
  if (value === 'teams') await loadTeams()
  if (value === 'checklists') await loadChecklistTemplates()
  if (value === 'response-templates') await loadResponseTemplates()
  if (value === 'automations') await loadAutomationRules()
  if (value === 'service-catalog') {
    await Promise.all([loadServiceCatalog(), loadChecklistTemplates(), loadSlaRules()])
  }
  if (value === 'approvals') await loadApprovals()
}

async function loadDashboard() {
  dashboardLoading.value = true
  try {
    const [operational, technician, recurrence] = await Promise.all([
      apiFetch('/it/dashboard/operational'),
      apiFetch('/it/dashboard/technician'),
      apiFetch('/it/dashboard/recurrences')
    ])
    dashboard.value = { ...operational, technician }
    recurrences.value = recurrence
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    dashboardLoading.value = false
  }
}

function openChecklistTemplate(template = null) {
  checklistForm.id = template?.id || null
  checklistForm.name = template?.name || ''
  checklistForm.category_id = template?.category_id || null
  checklistForm.type = template?.type || null
  checklistForm.items = (template?.items || [{ label: '', required: false, sort_order: 0 }]).map(item => ({ label: item.label, required: Boolean(item.required), sort_order: item.sort_order || 0 }))
  checklistDialog.value = true
}

async function loadChecklistTemplates() {
  try {
    const payload = await apiFetch('/it/checklist-templates')
    checklistTemplates.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadResponseTemplates() {
  try {
    const payload = await apiFetch('/it/response-templates')
    responseTemplates.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadAutomationRules() {
  try {
    const payload = await apiFetch('/it/automations')
    automationRules.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadServiceCatalog() {
  try {
    const payload = await apiFetch('/it/service-catalog')
    serviceCatalog.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadApprovals() {
  try {
    const payload = await apiFetch('/it/approvals?per_page=100')
    approvals.value = payload.data?.data || payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function decideApproval(approval, status) {
  $q.dialog({ title: status === 'aprovado' ? 'Aprovar serviço' : 'Rejeitar serviço', message: 'Comentário da decisão:', prompt: { model: '', type: 'textarea' }, cancel: true, persistent: true }).onOk(async comment => {
    try {
      await apiFetch(`/it/approvals/${approval.id}/decide`, { method: 'POST', body: JSON.stringify({ status, comment }) })
      await loadApprovals()
      $q.notify({ type: 'positive', message: 'Decisão registrada.' })
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

function openServiceCatalog(service = null) {
  Object.assign(serviceCatalogForm, {
    id: service?.id || null,
    name: service?.name || '',
    key: service?.key || '',
    description: service?.description || '',
    category_id: service?.category_id || null,
    type: service?.type || null,
    sla_rule_id: service?.sla_rule_id || null,
    checklist_template_id: service?.checklist_template_id || null,
    requires_approval: Boolean(service?.requires_approval),
    approval_role: service?.approval_role || '',
    active: service?.active !== false
  })
  serviceCatalogDialog.value = true
}

async function saveServiceCatalog() {
  try {
    const endpoint = serviceCatalogForm.id ? `/it/service-catalog/${serviceCatalogForm.id}` : '/it/service-catalog'
    await apiFetch(endpoint, { method: serviceCatalogForm.id ? 'PUT' : 'POST', body: JSON.stringify({ ...serviceCatalogForm }) })
    serviceCatalogDialog.value = false
    await loadServiceCatalog()
    $q.notify({ type: 'positive', message: 'Serviço salvo no catálogo.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function formatAutomationJson(value) {
  return JSON.stringify(value || {}, null, 0)
}

function openAutomationRule(rule = null) {
  Object.assign(automationForm, {
    id: rule?.id || null,
    name: rule?.name || '',
    event: rule?.event || 'ticket_created',
    conditionsText: JSON.stringify(rule?.conditions || {}, null, 2),
    actionsText: JSON.stringify(rule?.actions || [], null, 2),
    active: rule?.active !== false
  })
  automationDialog.value = true
}

async function saveAutomationRule() {
  try {
    const conditions = JSON.parse(automationForm.conditionsText || '{}')
    const actions = JSON.parse(automationForm.actionsText || '[]')
    if (!Array.isArray(actions) || !actions.length) throw new Error('Informe ao menos uma ação válida.')
    const endpoint = automationForm.id ? `/it/automations/${automationForm.id}` : '/it/automations'
    await apiFetch(endpoint, { method: automationForm.id ? 'PUT' : 'POST', body: JSON.stringify({ name: automationForm.name, event: automationForm.event, conditions, actions, active: automationForm.active }) })
    automationDialog.value = false
    await loadAutomationRules()
    $q.notify({ type: 'positive', message: 'Regra de automação salva.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof SyntaxError ? 'Condições ou ações devem ser JSON válido.' : error.message })
  }
}

async function toggleAutomationRule(rule, active) {
  try {
    await apiFetch(`/it/automations/${rule.id}`, { method: 'PUT', body: JSON.stringify({ active }) })
    rule.active = active
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function openResponseTemplate(template = null) {
  Object.assign(responseTemplateForm, {
    id: template?.id || null,
    name: template?.name || '',
    key: template?.key || '',
    body: template?.body || '',
    scope: template?.user_id ? 'pessoal' : (template?.support_team_id ? 'equipe' : 'global'),
    support_team_id: template?.support_team_id || null,
    active: template?.active !== false
  })
  responseTemplateDialog.value = true
}

async function saveResponseTemplate() {
  try {
    const endpoint = responseTemplateForm.id ? `/it/response-templates/${responseTemplateForm.id}` : '/it/response-templates'
    await apiFetch(endpoint, {
      method: responseTemplateForm.id ? 'PUT' : 'POST',
      body: JSON.stringify({ ...responseTemplateForm })
    })
    responseTemplateDialog.value = false
    await loadResponseTemplates()
    $q.notify({ type: 'positive', message: 'Template de resposta salvo.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function responseTemplateScope(template) {
  return template.user_id ? 'Pessoal' : (template.support_team_id ? `Equipe: ${template.support_team?.name || '-'}` : 'Global')
}

function applyResponseTemplate(id) {
  const template = responseTemplates.value.find(item => item.id === id)
  if (!template) return
  messageForm.message = String(template.body || '')
    .replaceAll('{{ticket_id}}', selectedTicket.value?.id || '')
    .replaceAll('{{solicitante}}', selectedTicket.value?.requester_name || '')
    .replaceAll('{{nome}}', selectedTicket.value?.requester_name || '')
}

async function saveChecklistTemplate() {
  try {
    const payload = { name: checklistForm.name, category_id: checklistForm.category_id, type: checklistForm.type, items: checklistForm.items }
    const endpoint = checklistForm.id ? `/it/checklist-templates/${checklistForm.id}` : '/it/checklist-templates'
    await apiFetch(endpoint, { method: checklistForm.id ? 'PUT' : 'POST', body: JSON.stringify(payload) })
    checklistDialog.value = false
    await loadChecklistTemplates()
    $q.notify({ type: 'positive', message: 'Template salvo.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadKnowledge() {
  try {
    const payload = await apiFetch('/it/knowledge?per_page=100')
    knowledgeArticles.value = payload.data?.data || payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadTeams() {
  try {
    const payload = await apiFetch('/it/support-teams')
    supportTeams.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function publishArticle(article, value) {
  try {
    const updated = await apiFetch(`/it/knowledge/${article.id}/publish`, { method: 'POST', body: JSON.stringify({ public: value }) })
    Object.assign(article, updated)
    $q.notify({ type: 'positive', message: value ? 'Artigo publicado.' : 'Artigo retirado da publicacao.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function queryString(data) {
  const params = new URLSearchParams()

  Object.entries(data).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, value)
    }
  })

  const query = params.toString()
  return query ? `?${query}` : ''
}

function clearFilters() {
  Object.assign(filters, { search: '', status: null, type: null, priority: null, category_id: null, department_id: null, assigned_to: null, source: null, sla: null, date_from: '', date_to: '' })
  ticketPagination.value.page = 1
  kanbanPagination.value.page = 1
  loadAll()
}

function defaultTicket() {
  return {
    id: null,
    department_id: null,
    assigned_to: null,
    support_team_id: null,
    category_id: null,
    type: 'incidente',
    requester_name: '',
    requester_phone: '',
    department_name: '',
    description: '',
    category: '',
    priority: 'media',
    impact: null,
    urgency: null,
    primary_asset_id: null,
    asset_ids: []
  }
}

function defaultCategory() {
  return { id: null, parent_id: null, name: '', description: '', active: true, color: 'primary', icon: 'category', sort_order: 0 }
}

function defaultSlaRule() {
  return {
    id: null,
    calendar_id: null,
    name: '',
    priority: null,
    category_id: null,
    type: null,
    department_id: null,
    first_response_minutes: 240,
    start_minutes: 0,
    resolution_minutes: 1440,
    rule_priority: 0,
    is_fallback: false,
    active: true
  }
}

function openTicket(row = null) {
  Object.assign(ticketForm, row ? { ...defaultTicket(), ...row } : defaultTicket())
  updateCalculatedPriority()
  ticketDialog.value = true
}

function updateCalculatedPriority() {
  const impact = ticketForm.impact
  const urgency = ticketForm.urgency
  if (!impact || !urgency) return
  const matrix = {
    'alto:alta': 'urgente',
    'alto:media': 'urgente',
    'medio:alta': 'urgente',
    'alto:baixa': 'alta',
    'medio:media': 'alta',
    'baixo:alta': 'alta',
    'medio:baixa': 'media',
    'baixo:media': 'media',
    'baixo:baixa': 'baixa'
  }
  ticketForm.priority = matrix[`${impact}:${urgency}`] || ticketForm.priority
}

function openCategory(row = null) {
  Object.assign(categoryForm, row ? { ...defaultCategory(), ...row } : defaultCategory())
  categoryDialog.value = true
}

function openSlaRule(row = null) {
  Object.assign(slaForm, row ? { ...defaultSlaRule(), ...row } : defaultSlaRule())
  slaDialog.value = true
}

function onFallbackToggle(value) {
  if (! value) return

  Object.assign(slaForm, { priority: null, category_id: null, type: null, department_id: null })
}

async function saveTicket() {
  const method = ticketForm.id ? 'PUT' : 'POST'
  const path = ticketForm.id ? `/it/tickets/${ticketForm.id}` : '/it/tickets'

  try {
    await apiFetch(path, { method, body: JSON.stringify(ticketForm) })
    $q.notify({ type: 'positive', message: 'Chamado salvo com sucesso.' })
    ticketDialog.value = false
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function saveCategory() {
  const method = categoryForm.id ? 'PUT' : 'POST'
  const path = categoryForm.id ? `/it/categories/${categoryForm.id}` : '/it/categories'

  try {
    await apiFetch(path, { method, body: JSON.stringify(categoryForm) })
    $q.notify({ type: 'positive', message: 'Categoria salva com sucesso.' })
    categoryDialog.value = false
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadCategories() {
  if (!canViewCategories.value) return

  try {
    const payload = await apiFetch(`/it/categories${queryString({ search: categorySearch.value })}`)
    categories.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadSlaRules() {
  if (!canManageSla.value) return

  try {
    const [ruleData, calendarData] = await Promise.all([
      apiFetch('/it/sla-rules'),
      apiFetch('/it/sla-calendars')
    ])
    slaRules.value = ruleData.data?.data || ruleData.data || []
    slaCalendars.value = calendarData.data || []
    slaTimezone.value = calendarData.timezone || 'UTC'
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function saveSlaRule() {
  const method = slaForm.id ? 'PUT' : 'POST'
  const path = slaForm.id ? `/it/sla-rules/${slaForm.id}` : '/it/sla-rules'

  try {
    await apiFetch(path, { method, body: JSON.stringify(slaForm) })
    $q.notify({ type: 'positive', message: 'Regra de SLA salva com sucesso.' })
    slaDialog.value = false
    await loadSlaRules()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function removeCategory(row) {
  $q.dialog({
    title: 'Confirmar exclusao',
    message: `Excluir a categoria ${row.name}? Categorias usadas por chamados serao apenas desativadas.`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      const payload = await apiFetch(`/it/categories/${row.id}`, { method: 'DELETE' })
      $q.notify({ type: 'positive', message: payload.message || 'Categoria removida.' })
      await loadAll()
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

async function inspectTicket(row) {
  detailLoading.value = true
  selectedTicket.value = null
  detailDialog.value = true
  try {
    selectedTicket.value = await apiFetch(`/it/tickets/${row.id}`)
    statusForm.status = selectedTicket.value.status
    statusForm.service_time_minutes = selectedTicket.value.service_time_minutes || 0
    statusForm.reason = ''
    statusForm.resolution_summary = selectedTicket.value.resolution_summary || ''
    statusForm.root_cause_id = selectedTicket.value.root_cause_id || null
    statusForm.identified_cause = selectedTicket.value.identified_cause || ''
    statusForm.root_cause = selectedTicket.value.root_cause || ''
    statusForm.applied_solution = selectedTicket.value.applied_solution || ''
    statusForm.executed_procedure = selectedTicket.value.executed_procedure || ''
    statusForm.replaced_part = selectedTicket.value.replaced_part || ''
    statusForm.requires_follow_up = Boolean(selectedTicket.value.requires_follow_up)
    statusForm.recurring_problem = Boolean(selectedTicket.value.recurring_problem)
    statusForm.internal_technical_note = selectedTicket.value.internal_technical_note || ''
    evaluationForm.rating = selectedTicket.value.evaluation?.rating || 5
    evaluationForm.comment = selectedTicket.value.evaluation?.comment || ''
    messageForm.message = ''
    messageForm.internal = false
    responseTemplateId.value = null
    attachmentFile.value = null
    await loadTicketAttachments(row.id)
    await loadResponseTemplates()
    const timelineData = await apiFetch(`/it/tickets/${row.id}/timeline`)
    timeline.value = timelineData.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
    detailDialog.value = false
  } finally {
    detailLoading.value = false
  }
}

async function changeStatus() {
  if (!selectedTicket.value) return
  if (!canSubmitStatus.value) return

  try {
    selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}/status`, {
      method: 'POST',
      body: JSON.stringify(statusForm)
    })
    selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
    $q.notify({ type: 'positive', message: 'Status atualizado.' })
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function cancelSelectedTicket() {
  if (!selectedTicket.value) return
  $q.dialog({
    title: 'Cancelar chamado',
    message: 'Informe o motivo do cancelamento. Esta ação ficará registrada no histórico.',
    prompt: { model: '', type: 'textarea', isValid: value => String(value || '').trim().length > 0 },
    cancel: true,
    persistent: true
  }).onOk(async reason => {
    try {
      selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason: String(reason).trim() })
      })
      $q.notify({ type: 'positive', message: 'Chamado cancelado e registrado no histórico.' })
      await loadAll()
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

function reflectIncident() {
  if (!selectedTicket.value) return
  $q.dialog({
    title: 'Atualizar incidentes filhos',
    message: 'Mensagem que será adicionada aos chamados relacionados:',
    prompt: { model: '', type: 'textarea' },
    cancel: true,
    persistent: true
  }).onOk(async message => {
    try {
      await apiFetch(`/it/tickets/${selectedTicket.value.id}/reflect-incident`, {
        method: 'POST',
        body: JSON.stringify({ message: String(message || '').trim() || 'Atualização do incidente principal.' })
      })
      selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
      $q.notify({ type: 'positive', message: 'Incidentes filhos atualizados.' })
      await loadAll()
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

async function moveKanbanTicket({ ticket, status }) {
  if (loading.value || !canEdit.value || !ticket || ticket.status === status) return

  if (!(ticket.available_transitions || []).includes(status)) {
    $q.notify({ type: 'warning', message: `Transicao de ${statusLabel(ticket.status)} para ${statusLabel(status)} nao permitida.` })
    return
  }

  if (['aguardando_usuario', 'resolvido'].includes(status)) {
    await inspectTicket(ticket)
    statusForm.status = status
    return
  }

  try {
    await apiFetch(`/it/tickets/${ticket.id}/status`, {
      method: 'POST',
      body: JSON.stringify({ status })
    })
    $q.notify({ type: 'positive', message: 'Status atualizado.' })
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function saveEvaluation() {
  if (!selectedTicket.value) return

  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/evaluation`, {
      method: 'POST',
      body: JSON.stringify(evaluationForm)
    })
    selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
    $q.notify({ type: 'positive', message: 'Avaliacao registrada.' })
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function sendMessage() {
  if (!selectedTicket.value) return

  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/messages`, {
      method: 'POST',
      body: JSON.stringify({
        message: messageForm.message,
        direction: requesterOnly.value ? 'inbound' : (messageForm.internal ? 'internal' : 'outbound'),
        visibility: messageForm.internal ? 'internal' : 'public'
      })
    })
    selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
    messageForm.message = ''
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function updateChecklistItem(item, checked) {
  if (!selectedTicket.value) return
  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/checklist-items/${item.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ status: checked ? 'concluido' : 'pendente' })
    })
    selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function updateTaskStatus(task, status) {
  if (!selectedTicket.value) return
  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/tasks/${task.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ status })
    })
    selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function refreshSelectedTicket() {
  if (selectedTicket.value) selectedTicket.value = await apiFetch(`/it/tickets/${selectedTicket.value.id}`)
}

async function addFollower() {
  if (!selectedTicket.value || !followerUserId.value) return
  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/followers`, { method: 'POST', body: JSON.stringify({ user_id: followerUserId.value }) })
    followerUserId.value = null
    await refreshSelectedTicket()
  } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
}

async function removeFollower(follower) {
  if (!selectedTicket.value) return
  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/followers/${follower.id}`, { method: 'DELETE' })
    await refreshSelectedTicket()
  } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
}

async function createTask() {
  if (!selectedTicket.value || !taskForm.title.trim()) return
  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/tasks`, { method: 'POST', body: JSON.stringify({ title: taskForm.title, status: 'pendente' }) })
    taskForm.title = ''
    await refreshSelectedTicket()
  } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
}

async function createRelationship() {
  if (!selectedTicket.value || !relationshipForm.related_ticket_id) return
  try {
    await apiFetch(`/it/tickets/${selectedTicket.value.id}/relationships`, { method: 'POST', body: JSON.stringify(relationshipForm) })
    relationshipForm.related_ticket_id = null
    await refreshSelectedTicket()
  } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
}

async function mergeDuplicate() {
  if (!selectedTicket.value || !relationshipForm.related_ticket_id) return
  $q.dialog({
    title: 'Mesclar chamado duplicado',
    message: `O chamado #${relationshipForm.related_ticket_id} será encerrado como duplicado deste chamado. Continuar?`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await apiFetch(`/it/tickets/${selectedTicket.value.id}/merge-duplicate`, {
        method: 'POST',
        body: JSON.stringify({ duplicate_ticket_id: relationshipForm.related_ticket_id })
      })
      relationshipForm.related_ticket_id = null
      await refreshSelectedTicket()
      await loadAll()
      $q.notify({ type: 'positive', message: 'Chamado duplicado mesclado.' })
    } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
  })
}

async function createKnowledgeArticle() {
  if (!selectedTicket.value) return
  try {
    await apiFetch(`/it/knowledge/from-ticket/${selectedTicket.value.id}`, { method: 'POST' })
    $q.notify({ type: 'positive', message: 'Artigo criado a partir da solução.' })
    if (tab.value === 'knowledge') await loadKnowledge()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

let suggestionTimer = null

function loadKnowledgeSuggestions() {
  clearTimeout(suggestionTimer)
  suggestionTimer = setTimeout(async () => {
    if (!String(ticketForm.description || '').trim()) {
      knowledgeSuggestions.value = []
      return
    }

    try {
      const payload = await apiFetch(`/it/knowledge/suggestions${queryString({ q: ticketForm.description, category_id: ticketForm.category_id })}`)
      knowledgeSuggestions.value = payload.data || []
    } catch {
      knowledgeSuggestions.value = []
    }
  }, 450)
 }

async function loadTicketAttachments(id) {
  const payload = await apiFetch(`/attachments/it-ticket/${id}`)
  attachments.value = payload.data || []
}

async function uploadTicketAttachment() {
  if (!selectedTicket.value || !attachmentFile.value) return

  uploadingAttachment.value = true

  try {
    const formData = new FormData()
    formData.append('file', attachmentFile.value)
    await apiUpload(`/attachments/it-ticket/${selectedTicket.value.id}`, formData)
    attachmentFile.value = null
    await loadTicketAttachments(selectedTicket.value.id)
    $q.notify({ type: 'positive', message: 'Anexo enviado.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    uploadingAttachment.value = false
  }
}

async function downloadAttachment(attachment) {
  try {
    await apiDownload(attachment.download_url, attachment.original_name)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function removeTicketAttachment(attachment) {
  $q.dialog({
    title: 'Remover anexo',
    message: `Remover ${attachment.original_name}?`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await apiFetch(`/attachments/${attachment.id}`, { method: 'DELETE' })
      await loadTicketAttachments(selectedTicket.value.id)
      $q.notify({ type: 'positive', message: 'Anexo removido.' })
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

function statusLabel(status) {
  return labelFor(status).replaceAll('_', ' ')
}

function timelineTitle(event) {
  return {
    status: 'Mudança de status',
    message: 'Mensagem',
    task: 'Tarefa',
    checklist: 'Checklist',
    attachment: 'Anexo',
    evaluation: 'Avaliação',
    sla: 'SLA',
    audit: 'Auditoria'
  }[event.type] || 'Evento'
}

function timelineDescription(event) {
  const data = event.data || {}
  if (event.type === 'status') return `${statusLabel(data.from_status || 'criação')} para ${statusLabel(data.to_status)}${data.changed_by?.name ? ` por ${data.changed_by.name}` : ''}`
  if (event.type === 'message') return data.message || 'Mensagem registrada.'
  if (event.type === 'task') return data.title || 'Tarefa atualizada.'
  if (event.type === 'checklist') return data.label || 'Item de checklist atualizado.'
  if (event.type === 'attachment') return data.original_name || 'Anexo registrado.'
  if (event.type === 'evaluation') return `Nota ${data.rating}/5${data.comment ? `: ${data.comment}` : ''}`
  if (event.type === 'sla') return `SLA definido: resolução até ${formatDateTime(data.due_at)}`
  return data.description || data.log_name || 'Alteração auditada.'
}

function labelFor(value) {
  return String(value || '').replaceAll('_', ' ')
}

function statusColor(status) {
  return {
    aberto: 'primary',
    triagem: 'warning',
    em_atendimento: 'info',
    aguardando_usuario: 'orange',
    resolvido: 'positive',
    encerrado: 'secondary',
    cancelado: 'negative'
  }[status] || 'grey'
}

function statusIcon(status) {
  return {
    aberto: 'forum',
    triagem: 'manage_search',
    em_atendimento: 'support_agent',
    aguardando_usuario: 'notifications',
    resolvido: 'task_alt',
    encerrado: 'check_circle',
    cancelado: 'cancel'
  }[status] || 'sync_alt'
}

function priorityColor(priority) {
  return {
    baixa: 'secondary',
    media: 'primary',
    alta: 'warning',
    urgente: 'negative'
  }[priority] || 'grey'
}

function typeMeta(type) {
  return (options.value.types || []).find(item => item.value === type) || { label: labelFor(type || 'incidente'), icon: 'label', color: 'primary' }
}

function buildCategoryOptions(items) {
  const byId = new Map(items.map(item => [item.id, item]))

  return [...items]
    .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0) || String(a.name).localeCompare(String(b.name)))
    .map(item => {
      const parent = item.parent || byId.get(item.parent_id)
      return {
        label: parent ? `${parent.name} / ${item.name}` : item.name,
        value: item.id
      }
    })
}

function filterCategoryOptions(value, update) {
  update(() => {
    const needle = String(value || '').toLowerCase()
    filteredCategoryOptions.value = categoryOptions.value.filter(option => option.label.toLowerCase().includes(needle))
  })
}

function slaScope(rule) {
  const parts = [
    optionLabel(priorityOptions, rule.priority),
    optionLabel(typeOptions.value, rule.type),
    optionLabel(categoryOptions.value, rule.category_id),
    optionLabel(departmentOptions.value, rule.department_id)
  ].filter(Boolean)

  return parts.length ? parts.join(' / ') : 'Regra geral'
}

function optionLabel(items, value) {
  return value ? items.find(item => item.value === value)?.label : null
}

function slaColor(value, status) {
  if (['resolvido', 'encerrado', 'cancelado'].includes(status)) return 'positive'
  if (!value) return 'grey'
  return new Date(value) < new Date() ? 'negative' : 'warning'
}

function formatFileSize(size) {
  const bytes = Number(size || 0)
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
</script>
