<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Gerencia de Transporte</div>
        <h1>Frota e solicitacoes</h1>
        <p>Cadastro de veiculos, motoristas e controle de solicitacoes com aprovacao e cancelamento.</p>
      </div>
      <div class="action-row">
        <q-btn v-if="canCreate" color="primary" icon="directions_car" label="Novo veiculo" unelevated @click="openVehicle()" />
        <q-btn v-if="canCreate" color="secondary" icon="badge" label="Novo motorista" unelevated @click="openDriver()" />
        <q-btn v-if="canCreate" color="accent" icon="route" label="Solicitar veiculo" unelevated @click="openRequest()" />
        <q-btn v-if="canCreate" color="warning" icon="settings" label="Manutencao" unelevated @click="openMaintenance()" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-tabs v-model="tab" dense align="left" active-color="primary" indicator-color="primary">
        <q-tab name="dashboard" icon="bar_chart" label="Painel" />
        <q-tab name="vehicles" icon="directions_car" label="Veiculos" />
        <q-tab name="drivers" icon="badge" label="Motoristas" />
        <q-tab name="requests" icon="route" label="Solicitacoes" />
        <q-tab name="maintenances" icon="settings" label="Manutencao" />
        <q-tab name="agenda" icon="event_available" label="Agenda" />
        <q-tab name="availability" icon="event_available" label="Disponibilidade" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated class="module-panels">
        <q-tab-panel name="dashboard">
          <div class="metric-grid">
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="route" color="primary" size="28px" />
                <div class="metric-value">{{ transportSummary.open || 0 }}</div>
                <div class="metric-label">Solicitacoes abertas</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="departure_board" color="info" size="28px" />
                <div class="metric-value">{{ transportSummary.in_progress || 0 }}</div>
                <div class="metric-label">Em deslocamento</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="speed" color="secondary" size="28px" />
                <div class="metric-value">{{ transportSummary.km_month || 0 }}</div>
                <div class="metric-label">KM no mes</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="metric-card">
              <q-card-section>
                <q-icon name="settings" color="warning" size="28px" />
                <div class="metric-value">{{ maintenanceSummary.overdue || 0 }}</div>
                <div class="metric-label">Manutencoes vencidas</div>
              </q-card-section>
            </q-card>
          </div>

          <div class="transport-overview-grid">
            <q-card flat bordered>
              <q-card-section>
                <div class="panel-title">Hoje</div>
                <q-list separator>
                  <q-item>
                    <q-item-section avatar><q-icon name="event_available" color="primary" /></q-item-section>
                    <q-item-section>
                      <q-item-label>{{ transportSummary.scheduled_today || 0 }} viagem(ns) agendada(s)</q-item-label>
                      <q-item-label caption>{{ transportSummary.completed_month || 0 }} concluida(s) no mes</q-item-label>
                    </q-item-section>
                  </q-item>
                  <q-item>
                    <q-item-section avatar><q-icon name="person" color="secondary" /></q-item-section>
                    <q-item-section>
                      <q-item-label>{{ transportSummary.top_driver?.driver?.name || 'Sem motorista recorrente' }}</q-item-label>
                      <q-item-label caption>Motorista mais acionado no mes</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-card-section>
            </q-card>
            <q-card flat bordered>
              <q-card-section>
                <div class="panel-title">Frota</div>
                <q-list separator>
                  <q-item>
                    <q-item-section avatar><q-icon name="directions_car" color="positive" /></q-item-section>
                    <q-item-section>
                      <q-item-label>{{ transportSummary.idle_vehicles || 0 }} veiculo(s) disponivel(is)</q-item-label>
                      <q-item-label caption>{{ maintenanceSummary.vehicles_in_maintenance || 0 }} em manutencao</q-item-label>
                    </q-item-section>
                  </q-item>
                  <q-item>
                    <q-item-section avatar><q-icon name="notifications" color="warning" /></q-item-section>
                    <q-item-section>
                      <q-item-label>{{ maintenanceSummary.soon || 0 }} manutencao(oes) proximas</q-item-label>
                      <q-item-label caption>{{ maintenanceSummary.open || 0 }} manutencao(oes) abertas</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-card-section>
            </q-card>
          </div>
        </q-tab-panel>

        <q-tab-panel name="vehicles">
          <q-table
            flat
            bordered
            title="Veiculos"
            :rows="vehicles"
            :columns="vehicleColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhum veiculo cadastrado"
          >
            <template #top-right>
              <q-input v-model="search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadAll">
                <template #prepend><q-icon name="search" /></template>
              </q-input>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openVehicle(props.row)">
                  <q-tooltip>Editar veiculo</q-tooltip>
                </q-btn>
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" @click="removeVehicle(props.row)">
                  <q-tooltip>Excluir veiculo</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="drivers">
          <q-table
            flat
            bordered
            title="Motoristas"
            :rows="drivers"
            :columns="driverColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhum motorista cadastrado"
          >
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openDriver(props.row)">
                  <q-tooltip>Editar motorista</q-tooltip>
                </q-btn>
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" @click="removeDriver(props.row)">
                  <q-tooltip>Excluir motorista</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="requests">
          <q-table
            flat
            bordered
            title="Solicitacoes"
            :rows="requests"
            :columns="requestColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhuma solicitacao cadastrada"
          >
            <template #top>
              <div class="transport-filters">
                <q-input v-model="requestFilters.search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadRequests">
                  <template #prepend><q-icon name="search" /></template>
                </q-input>
                <q-select v-model="requestFilters.status" dense outlined label="Status" :options="requestStatusOptions" emit-value map-options clearable @update:model-value="loadRequests" />
                <q-select v-model="requestFilters.department_id" dense outlined label="Setor" :options="departmentOptions" emit-value map-options clearable @update:model-value="loadRequests" />
                <q-select v-model="requestFilters.vehicle_id" dense outlined label="Veiculo" :options="vehicleOptions" emit-value map-options clearable @update:model-value="loadRequests" />
                <q-select v-model="requestFilters.driver_id" dense outlined label="Motorista" :options="driverOptions" emit-value map-options clearable @update:model-value="loadRequests" />
                <q-input v-model="requestFilters.date_from" dense outlined type="date" label="De" @update:model-value="loadRequests" />
                <q-input v-model="requestFilters.date_to" dense outlined type="date" label="Ate" @update:model-value="loadRequests" />
                <q-btn flat round icon="filter_alt" @click="clearRequestFilters">
                  <q-tooltip>Limpar filtros</q-tooltip>
                </q-btn>
              </div>
            </template>
            <template #body-cell-status="props">
              <q-td :props="props">
                <q-badge :color="statusColor(props.row.status)" outline>{{ statusLabel(props.row.status) }}</q-badge>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn flat round dense icon="visibility" @click="openRequestDetail(props.row)">
                  <q-tooltip>Detalhar solicitacao</q-tooltip>
                </q-btn>
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openRequest(props.row)">
                  <q-tooltip>Editar solicitacao</q-tooltip>
                </q-btn>
                <q-btn v-if="canApprove && nextStatuses(props.row).length" flat round dense icon="sync_alt" color="primary">
                  <q-tooltip>Alterar status</q-tooltip>
                  <q-menu>
                    <q-list style="min-width: 220px">
                      <q-item
                        v-for="status in nextStatuses(props.row)"
                        :key="status"
                        clickable
                        v-close-popup
                        @click="handleStatusAction(props.row, status)"
                      >
                        <q-item-section avatar>
                          <q-icon :name="statusIcon(status)" :color="statusColor(status)" />
                        </q-item-section>
                        <q-item-section>{{ statusLabel(status) }}</q-item-section>
                      </q-item>
                    </q-list>
                  </q-menu>
                </q-btn>
                <q-btn v-if="canApprove && nextStatuses(props.row).includes('aprovada')" flat round dense icon="check_circle" color="positive" @click="approveRequest(props.row)">
                  <q-tooltip>Aprovar</q-tooltip>
                </q-btn>
                <q-btn v-if="canCancel && nextStatuses(props.row).includes('cancelada')" flat round dense icon="cancel" color="negative" @click="cancelRequest(props.row)">
                  <q-tooltip>Cancelar</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="maintenances">
          <div class="section-toolbar">
            <div>
              <div class="text-h6">Manutencao preventiva</div>
              <div class="text-caption text-grey-7">Revisoes, oleo, pneus, licenciamento e historico da frota.</div>
            </div>
            <q-btn v-if="canCreate" color="warning" icon="add" label="Nova manutencao" unelevated @click="openMaintenance()" />
          </div>

          <div class="transport-filters q-mb-md">
            <q-input v-model="maintenanceFilters.search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadMaintenances">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="maintenanceFilters.status" dense outlined label="Status" :options="maintenanceStatusOptions" emit-value map-options clearable @update:model-value="loadMaintenances" />
            <q-select v-model="maintenanceFilters.type" dense outlined label="Tipo" :options="maintenanceTypeOptions" emit-value map-options clearable @update:model-value="loadMaintenances" />
            <q-select v-model="maintenanceFilters.vehicle_id" dense outlined label="Veiculo" :options="vehicleOptions" emit-value map-options clearable @update:model-value="loadMaintenances" />
            <q-select v-model="maintenanceFilters.due" dense outlined label="Prazo" :options="maintenanceDueOptions" emit-value map-options clearable @update:model-value="loadMaintenances" />
          </div>

          <q-table
            flat
            bordered
            title="Manutencoes"
            :rows="maintenances"
            :columns="maintenanceColumns"
            row-key="id"
            :loading="maintenanceLoading"
            no-data-label="Nenhuma manutencao cadastrada"
          >
            <template #body-cell-status="props">
              <q-td :props="props">
                <q-badge :color="maintenanceStatusColor(props.row)" outline>{{ statusLabel(props.row.status) }}</q-badge>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openMaintenance(props.row)">
                  <q-tooltip>Editar manutencao</q-tooltip>
                </q-btn>
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" @click="removeMaintenance(props.row)">
                  <q-tooltip>Excluir manutencao</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="agenda">
          <div class="section-toolbar">
            <div>
              <div class="text-h6">Agenda operacional</div>
              <div class="text-caption text-grey-7">Linha do tempo por periodo, veiculo e motorista.</div>
            </div>
            <div class="row q-gutter-sm items-center">
              <q-input v-model="agendaFilters.date_from" dense outlined type="date" label="De" @update:model-value="loadAgenda" />
              <q-input v-model="agendaFilters.date_to" dense outlined type="date" label="Ate" @update:model-value="loadAgenda" />
              <q-btn flat round icon="refresh" :loading="agendaLoading" @click="loadAgenda">
                <q-tooltip>Atualizar agenda</q-tooltip>
              </q-btn>
            </div>
          </div>
          <div class="transport-filters q-mb-md">
            <q-select v-model="agendaFilters.vehicle_id" dense outlined label="Veiculo" :options="vehicleOptions" emit-value map-options clearable @update:model-value="loadAgenda" />
            <q-select v-model="agendaFilters.driver_id" dense outlined label="Motorista" :options="driverOptions" emit-value map-options clearable @update:model-value="loadAgenda" />
          </div>
          <q-timeline color="primary" layout="comfortable">
            <q-timeline-entry
              v-for="item in agendaItems"
              :key="item.id"
              :title="`${item.departure_time} - ${item.origin} para ${item.destination}`"
              :subtitle="`${formatDate(item.date)} | ${item.vehicle}`"
              :icon="statusIcon(item.status)"
              :color="statusColor(item.status)"
            >
              <div class="text-body2">{{ item.driver }} - {{ item.department }}</div>
              <q-badge :color="statusColor(item.status)" outline>{{ statusLabel(item.status) }}</q-badge>
            </q-timeline-entry>
          </q-timeline>
          <div v-if="!agendaLoading && !agendaItems.length" class="empty-note">Nenhum item de agenda no periodo.</div>
        </q-tab-panel>

        <q-tab-panel name="availability">
          <div class="section-toolbar">
            <div>
              <div class="text-h6">Disponibilidade da frota</div>
              <div class="text-caption text-grey-7">Agenda por data, considerando solicitacoes ativas.</div>
            </div>
            <div class="row q-gutter-sm items-center">
              <q-input v-model="availabilityDate" dense outlined type="date" label="Data" @update:model-value="loadAvailability" />
              <q-btn flat round icon="refresh" :loading="availabilityLoading" @click="loadAvailability">
                <q-tooltip>Atualizar disponibilidade</q-tooltip>
              </q-btn>
            </div>
          </div>

          <q-table
            flat
            bordered
            title="Veiculos"
            :rows="availability"
            :columns="availabilityColumns"
            row-key="id"
            :loading="availabilityLoading"
            no-data-label="Nenhum veiculo cadastrado"
          >
            <template #body-cell-available="props">
              <q-td :props="props">
                <q-badge :color="props.row.available ? 'positive' : availabilityColor(props.row)" outline>
                  {{ props.row.available ? 'Livre' : availabilityLabel(props.row) }}
                </q-badge>
              </q-td>
            </template>
            <template #body-cell-schedule="props">
              <q-td :props="props">
                <div v-if="props.row.schedule.length" class="schedule-stack">
                  <q-chip
                    v-for="item in props.row.schedule"
                    :key="item.id"
                    dense
                    square
                    :color="statusColor(item.status)"
                    text-color="white"
                    clickable
                    @click="openRequestDetail(item)"
                  >
                    {{ item.departure_time }}-{{ item.expected_arrival_time || '??:??' }} #{{ item.id }}
                  </q-chip>
                </div>
                <span v-else class="text-grey-7">Sem agenda</span>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn
                  v-if="canCreate && props.row.available"
                  flat
                  round
                  dense
                  icon="add_road"
                  color="accent"
                  @click="openRequestFromAvailability(props.row)"
                >
                  <q-tooltip>Solicitar este veiculo</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>

          <q-table
            flat
            bordered
            title="Motoristas"
            class="q-mt-md"
            :rows="driverAvailability"
            :columns="driverAvailabilityColumns"
            row-key="id"
            :loading="availabilityLoading"
            no-data-label="Nenhum motorista cadastrado"
          >
            <template #body-cell-available="props">
              <q-td :props="props">
                <q-badge :color="props.row.available ? 'positive' : driverAvailabilityColor(props.row)" outline>
                  {{ props.row.available ? 'Livre' : driverAvailabilityLabel(props.row) }}
                </q-badge>
              </q-td>
            </template>
            <template #body-cell-schedule="props">
              <q-td :props="props">
                <div v-if="props.row.schedule.length" class="schedule-stack">
                  <q-chip
                    v-for="item in props.row.schedule"
                    :key="item.id"
                    dense
                    square
                    :color="statusColor(item.status)"
                    text-color="white"
                    clickable
                    @click="openRequestDetail(item)"
                  >
                    {{ item.departure_time }}-{{ item.expected_arrival_time || '??:??' }} #{{ item.id }}
                  </q-chip>
                </div>
                <span v-else class="text-grey-7">Sem agenda</span>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>
      </q-tab-panels>
    </section>

    <q-dialog v-model="vehicleDialog">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ vehicleForm.id ? 'Editar veiculo' : 'Novo veiculo' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveVehicle">
          <q-card-section class="form-grid">
            <q-input v-model="vehicleForm.brand" outlined label="Marca" :rules="[required]" />
            <q-input v-model="vehicleForm.model" outlined label="Modelo" :rules="[required]" />
            <q-input v-model.number="vehicleForm.year" outlined type="number" label="Ano" />
            <q-input v-model="vehicleForm.plate" outlined label="Placa" :rules="[required]" />
            <q-input v-model="vehicleForm.color" outlined label="Cor" />
            <q-input v-model.number="vehicleForm.capacity" outlined type="number" label="Capacidade" />
            <q-input v-model.number="vehicleForm.current_km" outlined type="number" label="KM atual" />
            <q-select v-model="vehicleForm.status" outlined label="Situacao" :options="vehicleStatuses" emit-value map-options />
            <q-input v-model="vehicleForm.notes" outlined type="textarea" label="Observacoes" class="span-2" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="driverDialog">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ driverForm.id ? 'Editar motorista' : 'Novo motorista' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveDriver">
          <q-card-section class="form-grid">
            <q-input v-model="driverForm.name" outlined label="Nome" :rules="[required]" class="span-2" />
            <q-input v-model="driverForm.cnh" outlined label="CNH" :rules="[required]" />
            <q-input v-model="driverForm.cnh_category" outlined label="Categoria" :rules="[required]" />
            <q-input v-model="driverForm.cnh_expires_at" outlined type="date" label="Validade" :rules="[required]" />
            <q-input v-model="driverForm.phone" outlined label="Telefone" />
            <q-select v-model="driverForm.status" outlined label="Situacao" :options="driverStatuses" emit-value map-options />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="maintenanceDialog">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ maintenanceForm.id ? 'Editar manutencao' : 'Nova manutencao' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveMaintenance">
          <q-card-section class="form-grid">
            <q-select v-model="maintenanceForm.vehicle_id" outlined label="Veiculo" :options="vehicleOptions" emit-value map-options :rules="[required]" />
            <q-select v-model="maintenanceForm.type" outlined label="Tipo" :options="maintenanceTypeOptions" emit-value map-options :rules="[required]" />
            <q-select v-model="maintenanceForm.status" outlined label="Status" :options="maintenanceStatusOptions" emit-value map-options />
            <q-input v-model="maintenanceForm.scheduled_at" outlined type="date" label="Agendada para" />
            <q-input v-model="maintenanceForm.completed_at" outlined type="date" label="Concluida em" />
            <q-input v-model.number="maintenanceForm.odometer" outlined type="number" label="KM da manutencao" />
            <q-input v-model.number="maintenanceForm.next_due_km" outlined type="number" label="Proximo KM" />
            <q-input v-model="maintenanceForm.next_due_at" outlined type="date" label="Proxima data" />
            <q-input v-model.number="maintenanceForm.cost" outlined type="number" step="0.01" label="Custo" />
            <q-input v-model="maintenanceForm.supplier" outlined label="Fornecedor" />
            <q-input v-model="maintenanceForm.description" outlined type="textarea" label="Descricao" :rules="[required]" class="span-2" />
            <q-input v-model="maintenanceForm.notes" outlined type="textarea" label="Observacoes" class="span-2" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="requestDialog">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ requestForm.id ? 'Editar solicitacao' : 'Solicitar veiculo' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveRequest">
          <q-card-section class="form-grid">
            <q-select v-model="requestForm.department_id" outlined label="Setor solicitante" :options="departmentOptions" emit-value map-options :rules="[required]" />
            <q-select v-model="requestForm.vehicle_id" outlined label="Veiculo" :options="vehicleOptions" emit-value map-options clearable />
            <q-select v-model="requestForm.driver_id" outlined label="Motorista" :options="driverOptions" emit-value map-options clearable />
            <q-input v-model="requestForm.responsible" outlined label="Responsavel" :rules="[required]" />
            <q-input v-model="requestForm.origin" outlined label="Origem" :rules="[required]" />
            <q-input v-model="requestForm.destination" outlined label="Destino" :rules="[required]" />
            <q-input v-model="requestForm.date" outlined type="date" label="Data" :rules="[required]" />
            <q-input v-model="requestForm.departure_time" outlined type="time" label="Hora saida" :rules="[required]" />
            <q-input v-model="requestForm.expected_arrival_time" outlined type="time" label="Previsao chegada" />
            <q-input v-model.number="requestForm.passengers_count" outlined type="number" label="Passageiros" :rules="[required]" />
            <q-input v-model.number="requestForm.initial_km" outlined type="number" label="KM inicial" />
            <q-input v-model.number="requestForm.final_km" outlined type="number" label="KM final" />
            <q-input v-model="requestForm.route_description" outlined type="textarea" label="Descricao/Roteiro" :rules="[required]" class="span-2" />
            <q-input v-model="requestForm.notes" outlined type="textarea" label="Observacoes" class="span-2" />
            <div v-if="requestForm.id" class="attachment-panel span-2">
              <div class="attachment-header">
                <div>
                  <div class="text-subtitle2">Anexos</div>
                  <div class="text-caption text-grey-7">{{ requestAttachments.length }} arquivo(s) vinculados</div>
                </div>
                <q-file
                  v-if="canEdit"
                  v-model="requestAttachmentFile"
                  dense
                  outlined
                  clearable
                  label="Selecionar arquivo"
                  class="attachment-picker"
                >
                  <template #prepend><q-icon name="attach_file" /></template>
                </q-file>
                <q-btn
                  v-if="canEdit"
                  color="primary"
                  icon="upload"
                  round
                  unelevated
                  :disable="!requestAttachmentFile"
                  :loading="uploadingRequestAttachment"
                  @click.prevent="uploadRequestAttachment"
                >
                  <q-tooltip>Enviar anexo</q-tooltip>
                </q-btn>
              </div>

              <q-list v-if="requestAttachments.length" bordered separator class="attachment-list">
                <q-item v-for="attachment in requestAttachments" :key="attachment.id">
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
                      <q-btn flat round dense icon="download" @click.prevent="downloadAttachment(attachment)">
                        <q-tooltip>Baixar anexo</q-tooltip>
                      </q-btn>
                      <q-btn v-if="canEdit" flat round dense icon="delete" color="negative" @click.prevent="removeRequestAttachment(attachment)">
                        <q-tooltip>Remover anexo</q-tooltip>
                      </q-btn>
                    </div>
                  </q-item-section>
                </q-item>
              </q-list>
              <div v-else class="empty-note">Nenhum anexo registrado.</div>
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="requestDetailDialog">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-h6">Solicitacao #{{ selectedRequest?.id }}</div>
            <div class="text-caption text-grey-7">{{ selectedRequest?.department?.name || 'Setor nao informado' }}</div>
          </div>
          <div class="row q-gutter-xs items-center">
            <q-badge v-if="selectedRequest" :color="statusColor(selectedRequest.status)" outline>
              {{ statusLabel(selectedRequest.status) }}
            </q-badge>
            <q-btn flat round dense icon="close" v-close-popup />
          </div>
        </q-card-section>
        <q-separator />
        <q-card-section v-if="selectedRequest" class="detail-grid">
          <div>
            <span>Roteiro</span>
            <strong>{{ selectedRequest.origin }} para {{ selectedRequest.destination }}</strong>
            <p>{{ selectedRequest.route_description }}</p>
          </div>
          <div>
            <span>Agenda</span>
            <strong>{{ formatDate(selectedRequest.date) }} - {{ selectedRequest.departure_time }} ate {{ selectedRequest.expected_arrival_time || 'sem previsao' }}</strong>
            <p>{{ selectedRequest.passengers_count }} passageiro(s)</p>
          </div>
          <div>
            <span>Quilometragem</span>
            <strong>{{ selectedRequest.initial_km || '-' }} ate {{ selectedRequest.final_km || '-' }}</strong>
            <p>{{ distanceLabel(selectedRequest) }}</p>
          </div>
          <div>
            <span>Veiculo</span>
            <strong>{{ selectedRequest.vehicle ? `${selectedRequest.vehicle.plate} - ${selectedRequest.vehicle.brand} ${selectedRequest.vehicle.model}` : 'Nao definido' }}</strong>
            <p>{{ selectedRequest.vehicle?.capacity || '-' }} lugares - {{ statusLabel(selectedRequest.vehicle?.status) }}</p>
          </div>
          <div>
            <span>Motorista</span>
            <strong>{{ selectedRequest.driver?.name || 'Nao definido' }}</strong>
            <p>{{ selectedRequest.driver?.phone || 'Sem telefone' }}</p>
          </div>
          <div>
            <span>Responsavel</span>
            <strong>{{ selectedRequest.responsible }}</strong>
            <p>Solicitado por {{ selectedRequest.requester?.name || '-' }}</p>
          </div>
          <div>
            <span>Aprovacao</span>
            <strong>{{ selectedRequest.approver?.name || 'Nao aprovada' }}</strong>
            <p>{{ formatDateTime(selectedRequest.approved_at) }}</p>
          </div>
          <div class="span-2">
            <span>Observacoes</span>
            <p>{{ selectedRequest.notes || 'Nenhuma observacao registrada.' }}</p>
          </div>
          <div class="span-2">
            <span>Checklists</span>
            <q-list v-if="selectedRequest.checklists?.length" bordered separator class="compact-list">
              <q-item v-for="checklist in selectedRequest.checklists" :key="checklist.id">
                <q-item-section avatar>
                  <q-icon :name="checklist.type === 'saida' ? 'departure_board' : 'task_alt'" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ checklist.type === 'saida' ? 'Saida' : 'Retorno' }} - KM {{ checklist.km }}</q-item-label>
                  <q-item-label caption>
                    Combustivel {{ checklist.fuel_level ?? '-' }}% - {{ checklist.checked_by?.name || 'sem usuario' }} - {{ formatDateTime(checklist.checked_at) }}
                  </q-item-label>
                  <q-item-label v-if="checklist.has_damage" caption class="text-negative">
                    {{ checklist.damages_description }}
                  </q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
            <p v-else>Nenhum checklist registrado.</p>
          </div>
          <div class="span-2">
            <span>Historico de status</span>
            <q-list v-if="selectedRequest.status_histories?.length" bordered separator class="compact-list">
              <q-item v-for="history in selectedRequest.status_histories" :key="history.id">
                <q-item-section avatar>
                  <q-icon :name="statusIcon(history.to_status)" :color="statusColor(history.to_status)" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ statusLabel(history.from_status || 'criacao') }} para {{ statusLabel(history.to_status) }}</q-item-label>
                  <q-item-label caption>
                    {{ history.changed_by?.name || 'sem usuario' }} - {{ formatDateTime(history.created_at) }}
                  </q-item-label>
                  <q-item-label v-if="history.reason" caption>{{ history.reason }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
            <p v-else>Nenhum historico registrado.</p>
          </div>
        </q-card-section>
        <q-card-actions class="request-detail-actions">
          <q-btn
            v-for="status in selectedRequestNextStatuses"
            :key="status"
            flat
            :icon="statusIcon(status)"
            :color="statusColor(status)"
            :label="statusLabel(status)"
            no-caps
            @click="handleSelectedStatusAction(status)"
          />
          <q-btn v-if="selectedRequest && canEdit" color="primary" icon="edit" label="Editar" unelevated no-caps @click="editSelectedRequest" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="checklistDialog">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-h6">{{ checklistTargetStatus === 'em_deslocamento' ? 'Checklist de saida' : 'Checklist de retorno' }}</div>
            <div class="text-caption text-grey-7">Solicitacao #{{ checklistRequest?.id }}</div>
          </div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveChecklist">
          <q-card-section class="form-grid">
            <q-input v-model.number="checklistForm.km" outlined type="number" label="KM" :rules="[required]" />
            <q-input v-model.number="checklistForm.fuel_level" outlined type="number" label="Combustivel (%)" min="0" max="100" />
            <q-toggle v-model="checklistForm.documents_ok" label="Documentos conferidos" />
            <q-toggle v-model="checklistForm.tires_ok" label="Pneus em condicao" />
            <q-toggle v-model="checklistForm.lights_ok" label="Luzes em condicao" />
            <q-toggle v-model="checklistForm.bodywork_ok" label="Lataria sem avarias novas" />
            <q-toggle v-model="checklistForm.tools_ok" label="Itens obrigatorios conferidos" />
            <q-toggle v-model="checklistForm.has_damage" label="Ha avarias ou danos" color="negative" />
            <q-input
              v-if="checklistForm.has_damage || !checklistForm.bodywork_ok"
              v-model="checklistForm.damages_description"
              outlined
              type="textarea"
              label="Descricao das avarias"
              class="span-2"
              :rules="[required]"
            />
            <q-input v-model="checklistForm.observations" outlined type="textarea" label="Observacoes" class="span-2" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Registrar" unelevated :loading="checklistSubmitting" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { apiDownload, apiFetch, apiUpload } from '@/services/api'
import { useAuthStore } from '@/stores/auth-store'
import { formatDate, formatDateTime } from '@/composables/useDateFormat'

const $q = useQuasar()
const auth = useAuthStore()
const tab = ref('dashboard')
const loading = ref(false)
const search = ref('')
const vehicles = ref([])
const drivers = ref([])
const requests = ref([])
const maintenances = ref([])
const options = ref({ departments: [], vehicles: [], drivers: [] })
const availability = ref([])
const driverAvailability = ref([])
const transportSummary = ref({})
const maintenanceSummary = ref({})
const agendaItems = ref([])
const availabilityDate = ref(new Date().toISOString().slice(0, 10))
const availabilityLoading = ref(false)
const maintenanceLoading = ref(false)
const agendaLoading = ref(false)
const vehicleDialog = ref(false)
const driverDialog = ref(false)
const maintenanceDialog = ref(false)
const requestDialog = ref(false)
const requestDetailDialog = ref(false)
const checklistDialog = ref(false)
const selectedRequest = ref(null)
const checklistRequest = ref(null)
const checklistTargetStatus = ref(null)
const requestAttachments = ref([])
const requestAttachmentFile = ref(null)
const uploadingRequestAttachment = ref(false)
const checklistSubmitting = ref(false)

const required = value => value !== null && value !== undefined && value !== '' || 'Campo obrigatorio'

const vehicleStatuses = [
  { label: 'Disponivel', value: 'disponivel' },
  { label: 'Em uso', value: 'em_uso' },
  { label: 'Manutencao', value: 'manutencao' },
  { label: 'Inativo', value: 'inativo' }
]

const driverStatuses = [
  { label: 'Ativo', value: 'ativo' },
  { label: 'Afastado', value: 'afastado' },
  { label: 'Inativo', value: 'inativo' }
]

const requestStatusOptions = [
  { label: 'Solicitacao', value: 'solicitacao' },
  { label: 'Analise', value: 'analise' },
  { label: 'Aprovada', value: 'aprovada' },
  { label: 'Em deslocamento', value: 'em_deslocamento' },
  { label: 'Concluida', value: 'concluida' },
  { label: 'Cancelada', value: 'cancelada' },
  { label: 'Rejeitada', value: 'rejeitada' }
]

const maintenanceTypeOptions = [
  { label: 'Revisao', value: 'revisao' },
  { label: 'Oleo', value: 'oleo' },
  { label: 'Pneus', value: 'pneus' },
  { label: 'Licenciamento', value: 'licenciamento' },
  { label: 'Corretiva', value: 'corretiva' },
  { label: 'Outros', value: 'outros' }
]

const maintenanceStatusOptions = [
  { label: 'Agendada', value: 'agendada' },
  { label: 'Em execucao', value: 'em_execucao' },
  { label: 'Concluida', value: 'concluida' },
  { label: 'Cancelada', value: 'cancelada' }
]

const maintenanceDueOptions = [
  { label: 'Vencidas', value: 'overdue' },
  { label: 'Proximas', value: 'soon' }
]

const vehicleForm = reactive(defaultVehicle())
const driverForm = reactive(defaultDriver())
const requestForm = reactive(defaultRequest())
const checklistForm = reactive(defaultChecklist())
const maintenanceForm = reactive(defaultMaintenance())
const requestFilters = reactive({ search: '', status: null, department_id: null, vehicle_id: null, driver_id: null, date_from: '', date_to: '' })
const maintenanceFilters = reactive({ search: '', status: null, type: null, vehicle_id: null, due: null })
const agendaFilters = reactive({
  date_from: new Date().toISOString().slice(0, 10),
  date_to: new Date(Date.now() + 6 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
  vehicle_id: null,
  driver_id: null
})

const vehicleColumns = [
  { name: 'plate', label: 'Placa', field: 'plate', align: 'left', sortable: true },
  { name: 'brand', label: 'Marca', field: 'brand', align: 'left' },
  { name: 'model', label: 'Modelo', field: 'model', align: 'left' },
  { name: 'status', label: 'Situacao', field: 'status', align: 'left' },
  { name: 'current_km', label: 'KM', field: 'current_km', align: 'right' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const driverColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left' },
  { name: 'cnh', label: 'CNH', field: 'cnh', align: 'left' },
  { name: 'cnh_category', label: 'Categoria', field: 'cnh_category', align: 'left' },
  { name: 'cnh_expires_at', label: 'Validade', field: 'cnh_expires_at', align: 'left' },
  { name: 'status', label: 'Situacao', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const requestColumns = [
  { name: 'date', label: 'Data', field: 'date', align: 'left' },
  { name: 'department', label: 'Setor', field: row => row.department?.name, align: 'left' },
  { name: 'vehicle', label: 'Veiculo', field: row => row.vehicle?.plate || '-', align: 'left' },
  { name: 'driver', label: 'Motorista', field: row => row.driver?.name || '-', align: 'left' },
  { name: 'status', label: 'Situacao', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const availabilityColumns = [
  { name: 'plate', label: 'Placa', field: 'plate', align: 'left', sortable: true },
  { name: 'vehicle', label: 'Veiculo', field: row => `${row.brand} ${row.model}`, align: 'left' },
  { name: 'capacity', label: 'Lugares', field: 'capacity', align: 'right' },
  { name: 'available', label: 'Disponibilidade', field: 'available', align: 'left' },
  { name: 'schedule', label: 'Agenda', field: 'schedule', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const driverAvailabilityColumns = [
  { name: 'name', label: 'Motorista', field: 'name', align: 'left', sortable: true },
  { name: 'cnh_category', label: 'CNH', field: 'cnh_category', align: 'left' },
  { name: 'cnh_expires_at', label: 'Validade', field: 'cnh_expires_at', align: 'left', format: value => formatDate(value) },
  { name: 'available', label: 'Disponibilidade', field: 'available', align: 'left' },
  { name: 'schedule', label: 'Agenda', field: 'schedule', align: 'left' }
]

const maintenanceColumns = [
  { name: 'vehicle', label: 'Veiculo', field: row => row.vehicle?.plate || '-', align: 'left', sortable: true },
  { name: 'type', label: 'Tipo', field: 'type', align: 'left', format: value => statusLabel(value) },
  { name: 'status', label: 'Status', field: 'status', align: 'left' },
  { name: 'scheduled_at', label: 'Agendada', field: 'scheduled_at', align: 'left', format: value => formatDate(value) },
  { name: 'next_due_at', label: 'Prox. data', field: 'next_due_at', align: 'left', format: value => formatDate(value) },
  { name: 'next_due_km', label: 'Prox. KM', field: 'next_due_km', align: 'right' },
  { name: 'cost', label: 'Custo', field: 'cost', align: 'right', format: value => formatMoney(value) },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const departmentOptions = computed(() => options.value.departments.map(item => ({ label: item.name, value: item.id })))
const vehicleOptions = computed(() => options.value.vehicles.map(item => ({ label: `${item.plate} - ${item.brand} ${item.model}${item.capacity ? ` (${item.capacity} lugares)` : ''}`, value: item.id })))
const driverOptions = computed(() => options.value.drivers.map(item => ({ label: `${item.name} - ${item.cnh_category}`, value: item.id })))
const canCreate = computed(() => auth.can('transporte.cadastrar'))
const canEdit = computed(() => auth.can('transporte.editar'))
const canDelete = computed(() => auth.can('transporte.excluir'))
const canApprove = computed(() => auth.can('transporte.aprovar'))
const canCancel = computed(() => auth.can('transporte.cancelar'))
const selectedRequestNextStatuses = computed(() => selectedRequest.value ? nextStatuses(selectedRequest.value) : [])

onMounted(loadAll)

async function loadAll() {
  loading.value = true

  try {
    const [vehicleData, driverData, requestData, optionData, availabilityData, transportSummaryData, maintenanceSummaryData, maintenanceData, agendaData] = await Promise.all([
      apiFetch(`/transport/vehicles?search=${encodeURIComponent(search.value)}`),
      apiFetch('/transport/drivers'),
      apiFetch(`/transport/requests${queryString(requestFilters)}`),
      apiFetch('/transport/options'),
      apiFetch(`/transport/availability?date=${availabilityDate.value}`),
      apiFetch('/transport/summary'),
      apiFetch('/transport/maintenances/summary'),
      apiFetch(`/transport/maintenances${queryString(maintenanceFilters)}`),
      apiFetch(`/transport/agenda${queryString(agendaFilters)}`)
    ])

    vehicles.value = vehicleData.data || []
    drivers.value = driverData.data || []
    requests.value = requestData.data || []
    maintenances.value = maintenanceData.data || []
    options.value = optionData
    availability.value = availabilityData.vehicles || []
    driverAvailability.value = availabilityData.drivers || []
    transportSummary.value = transportSummaryData
    maintenanceSummary.value = maintenanceSummaryData
    agendaItems.value = agendaData.items || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

function queryString(filters) {
  const params = new URLSearchParams()

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, value)
    }
  })

  const query = params.toString()
  return query ? `?${query}` : ''
}

function defaultVehicle() {
  return { id: null, brand: '', model: '', year: null, plate: '', color: '', capacity: null, current_km: 0, status: 'disponivel', notes: '' }
}

function defaultDriver() {
  return { id: null, name: '', cnh: '', cnh_category: 'B', cnh_expires_at: '', phone: '', status: 'ativo' }
}

function defaultRequest() {
  return {
    id: null,
    department_id: null,
    vehicle_id: null,
    driver_id: null,
    route_description: '',
    origin: '',
    destination: '',
    date: new Date().toISOString().slice(0, 10),
    departure_time: '08:00',
    expected_arrival_time: '',
    passengers_count: 1,
    initial_km: null,
    final_km: null,
    responsible: '',
    notes: ''
  }
}

function defaultMaintenance() {
  return {
    id: null,
    vehicle_id: null,
    type: 'revisao',
    status: 'agendada',
    scheduled_at: new Date().toISOString().slice(0, 10),
    completed_at: '',
    odometer: null,
    next_due_km: null,
    next_due_at: '',
    cost: null,
    supplier: '',
    description: '',
    notes: ''
  }
}

function defaultChecklist() {
  return {
    fuel_level: null,
    km: null,
    documents_ok: true,
    tires_ok: true,
    lights_ok: true,
    bodywork_ok: true,
    tools_ok: true,
    has_damage: false,
    damages_description: '',
    observations: ''
  }
}

function assignForm(target, data) {
  Object.assign(target, data)
}

function openVehicle(row = null) {
  assignForm(vehicleForm, row ? { ...defaultVehicle(), ...row } : defaultVehicle())
  vehicleDialog.value = true
}

function openDriver(row = null) {
  assignForm(driverForm, row ? { ...defaultDriver(), ...row } : defaultDriver())
  driverDialog.value = true
}

function openMaintenance(row = null) {
  assignForm(maintenanceForm, row ? {
    ...defaultMaintenance(),
    ...row,
    vehicle_id: row.vehicle_id || row.vehicle?.id || null
  } : { ...defaultMaintenance(), vehicle_id: vehicleOptions.value[0]?.value || null })
  maintenanceDialog.value = true
}

async function openRequest(row = null) {
  assignForm(requestForm, row ? { ...defaultRequest(), ...row } : { ...defaultRequest(), department_id: departmentOptions.value[0]?.value || null })
  requestAttachmentFile.value = null
  requestAttachments.value = []
  if (row?.id) {
    await loadRequestAttachments(row.id)
  }
  requestDialog.value = true
}

function openRequestFromAvailability(vehicle) {
  assignForm(requestForm, {
    ...defaultRequest(),
    department_id: departmentOptions.value[0]?.value || null,
    vehicle_id: vehicle.id,
    date: availabilityDate.value,
    passengers_count: 1
  })
  requestAttachmentFile.value = null
  requestAttachments.value = []
  tab.value = 'requests'
  requestDialog.value = true
}

async function openRequestDetail(row) {
  try {
    selectedRequest.value = await apiFetch(`/transport/requests/${row.id}`)
    requestDetailDialog.value = true
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function saveVehicle() {
  const method = vehicleForm.id ? 'PUT' : 'POST'
  const path = vehicleForm.id ? `/transport/vehicles/${vehicleForm.id}` : '/transport/vehicles'
  await save(path, method, vehicleForm, () => { vehicleDialog.value = false })
}

async function saveDriver() {
  const method = driverForm.id ? 'PUT' : 'POST'
  const path = driverForm.id ? `/transport/drivers/${driverForm.id}` : '/transport/drivers'
  await save(path, method, driverForm, () => { driverDialog.value = false })
}

async function saveRequest() {
  const method = requestForm.id ? 'PUT' : 'POST'
  const path = requestForm.id ? `/transport/requests/${requestForm.id}` : '/transport/requests'
  await save(path, method, requestForm, () => { requestDialog.value = false })
}

async function saveMaintenance() {
  const method = maintenanceForm.id ? 'PUT' : 'POST'
  const path = maintenanceForm.id ? `/transport/maintenances/${maintenanceForm.id}` : '/transport/maintenances'
  await save(path, method, maintenanceForm, () => { maintenanceDialog.value = false })
}

async function loadRequests() {
  try {
    const payload = await apiFetch(`/transport/requests${queryString(requestFilters)}`)
    requests.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadMaintenances() {
  maintenanceLoading.value = true

  try {
    const [payload, summary] = await Promise.all([
      apiFetch(`/transport/maintenances${queryString(maintenanceFilters)}`),
      apiFetch('/transport/maintenances/summary')
    ])
    maintenances.value = payload.data || []
    maintenanceSummary.value = summary
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    maintenanceLoading.value = false
  }
}

async function loadAgenda() {
  agendaLoading.value = true

  try {
    const payload = await apiFetch(`/transport/agenda${queryString(agendaFilters)}`)
    agendaItems.value = payload.items || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    agendaLoading.value = false
  }
}

function clearRequestFilters() {
  Object.assign(requestFilters, { search: '', status: null, department_id: null, vehicle_id: null, driver_id: null, date_from: '', date_to: '' })
  loadRequests()
}

async function save(path, method, form, afterSave) {
  try {
    await apiFetch(path, { method, body: JSON.stringify(form) })
    $q.notify({ type: 'positive', message: 'Registro salvo com sucesso.' })
    afterSave()
    await loadAll()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function removeVehicle(row) {
  await remove(`/transport/vehicles/${row.id}`, `Excluir o veiculo ${row.plate}?`)
}

async function removeDriver(row) {
  await remove(`/transport/drivers/${row.id}`, `Excluir o motorista ${row.name}?`)
}

async function removeMaintenance(row) {
  await remove(`/transport/maintenances/${row.id}`, `Excluir a manutencao do veiculo ${row.vehicle?.plate || row.vehicle_id}?`)
}

async function remove(path, message) {
  $q.dialog({ title: 'Confirmar exclusao', message, cancel: true, persistent: true }).onOk(async () => {
    try {
      await apiFetch(path, { method: 'DELETE' })
      $q.notify({ type: 'positive', message: 'Registro removido.' })
      await loadAll()
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

async function approveRequest(row) {
  await changeRequestStatus(`/transport/requests/${row.id}/approve`, 'Solicitacao aprovada.')
}

async function cancelRequest(row) {
  promptStatusReason(row, 'cancelada', `/transport/requests/${row.id}/cancel`)
}

async function changeRequestStatus(path, message, status = null, reason = null) {
  try {
    await apiFetch(path, {
      method: 'POST',
      ...((status || reason) ? { body: JSON.stringify({ ...(status ? { status } : {}), ...(reason ? { reason } : {}) }) } : {})
    })
    $q.notify({ type: 'positive', message })
    await loadAll()
    return true
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
    return false
  }
}

async function changeRequestStatusTo(row, status) {
  return changeRequestStatus(`/transport/requests/${row.id}/status`, `Solicitacao alterada para ${statusLabel(status)}.`, status)
}

function handleStatusAction(row, status) {
  if (['em_deslocamento', 'concluida'].includes(status)) {
    openChecklist(row, status)
    return
  }

  if (['cancelada', 'rejeitada'].includes(status)) {
    promptStatusReason(row, status)
    return
  }

  changeRequestStatusTo(row, status)
}

async function handleSelectedStatusAction(status) {
  if (!selectedRequest.value) return

  if (['em_deslocamento', 'concluida'].includes(status)) {
    await openChecklist(selectedRequest.value, status)
    return
  }

  if (['cancelada', 'rejeitada'].includes(status)) {
    promptStatusReason(selectedRequest.value, status)
    return
  }

  await changeSelectedRequestStatus(status)
}

function promptStatusReason(row, status, path = null) {
  $q.dialog({
    title: status === 'cancelada' ? 'Motivo do cancelamento' : 'Motivo da rejeicao',
    message: `Informe o motivo para alterar para ${statusLabel(status)}.`,
    prompt: {
      model: '',
      type: 'textarea',
      isValid: value => String(value || '').trim().length > 0
    },
    cancel: true,
    persistent: true
  }).onOk(async reason => {
    const targetPath = path || `/transport/requests/${row.id}/status`
    const changed = await changeRequestStatus(targetPath, `Solicitacao alterada para ${statusLabel(status)}.`, path ? null : status, reason)

    if (changed && selectedRequest.value?.id === row.id) {
      selectedRequest.value = await apiFetch(`/transport/requests/${row.id}`)
    }
  })
}

async function openChecklist(row, status) {
  try {
    const payload = await apiFetch(`/transport/requests/${row.id}`)
    checklistRequest.value = payload
    checklistTargetStatus.value = status
    assignForm(checklistForm, {
      ...defaultChecklist(),
      km: status === 'em_deslocamento'
        ? payload.initial_km || payload.vehicle?.current_km || 0
        : payload.final_km || payload.initial_km || payload.vehicle?.current_km || 0
    })
    checklistDialog.value = true
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function saveChecklist() {
  if (!checklistRequest.value || !checklistTargetStatus.value) return

  checklistSubmitting.value = true

  try {
    const endpoint = checklistTargetStatus.value === 'em_deslocamento'
      ? 'departure-checklist'
      : 'return-checklist'
    const updated = await apiFetch(`/transport/requests/${checklistRequest.value.id}/${endpoint}`, {
      method: 'POST',
      body: JSON.stringify(checklistForm)
    })

    $q.notify({ type: 'positive', message: 'Checklist registrado.' })
    checklistDialog.value = false
    await loadAll()

    if (selectedRequest.value?.id === updated.id || requestDetailDialog.value) {
      selectedRequest.value = updated
      requestDetailDialog.value = true
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    checklistSubmitting.value = false
  }
}

async function changeSelectedRequestStatus(status) {
  if (!selectedRequest.value) return

  const changed = await changeRequestStatusTo(selectedRequest.value, status)

  if (changed) {
    selectedRequest.value = await apiFetch(`/transport/requests/${selectedRequest.value.id}`)
  }
}

function editSelectedRequest() {
  if (!selectedRequest.value) return

  requestDetailDialog.value = false
  openRequest(selectedRequest.value)
}

async function loadAvailability() {
  availabilityLoading.value = true

  try {
    const payload = await apiFetch(`/transport/availability?date=${availabilityDate.value}`)
    availability.value = payload.vehicles || []
    driverAvailability.value = payload.drivers || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    availabilityLoading.value = false
  }
}

async function loadRequestAttachments(id) {
  const payload = await apiFetch(`/attachments/vehicle-request/${id}`)
  requestAttachments.value = payload.data || []
}

async function uploadRequestAttachment() {
  if (!requestForm.id || !requestAttachmentFile.value) return

  uploadingRequestAttachment.value = true

  try {
    const formData = new FormData()
    formData.append('file', requestAttachmentFile.value)
    await apiUpload(`/attachments/vehicle-request/${requestForm.id}`, formData)
    requestAttachmentFile.value = null
    await loadRequestAttachments(requestForm.id)
    $q.notify({ type: 'positive', message: 'Anexo enviado.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    uploadingRequestAttachment.value = false
  }
}

async function downloadAttachment(attachment) {
  try {
    await apiDownload(attachment.download_url, attachment.original_name)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function removeRequestAttachment(attachment) {
  $q.dialog({
    title: 'Remover anexo',
    message: `Remover ${attachment.original_name}?`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await apiFetch(`/attachments/${attachment.id}`, { method: 'DELETE' })
      await loadRequestAttachments(requestForm.id)
      $q.notify({ type: 'positive', message: 'Anexo removido.' })
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

function formatFileSize(size) {
  const bytes = Number(size || 0)
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

function distanceLabel(request) {
  const initial = Number(request.initial_km || 0)
  const final = Number(request.final_km || 0)

  if (!initial || !final || final < initial) return 'Distancia nao informada'

  return `${final - initial} km percorridos`
}

function statusLabel(status) {
  return String(status || '').replaceAll('_', ' ')
}

function nextStatuses(row) {
  return row.available_transitions || []
}

function statusIcon(status) {
  return {
    analise: 'manage_search',
    aprovada: 'check_circle',
    em_deslocamento: 'departure_board',
    concluida: 'task_alt',
    cancelada: 'cancel',
    rejeitada: 'block'
  }[status] || 'sync_alt'
}

function statusColor(status) {
  return {
    solicitacao: 'primary',
    analise: 'warning',
    aprovada: 'positive',
    em_deslocamento: 'info',
    concluida: 'secondary',
    cancelada: 'negative',
    rejeitada: 'negative'
  }[status] || 'grey'
}

function maintenanceStatusColor(row) {
  if (row.status === 'concluida') return 'positive'
  if (row.status === 'cancelada') return 'grey'
  if (row.status === 'em_execucao') return 'info'
  if (isMaintenanceOverdue(row)) return 'negative'
  if (isMaintenanceSoon(row)) return 'warning'
  return 'primary'
}

function isMaintenanceOverdue(row) {
  const today = new Date().toISOString().slice(0, 10)
  const currentKm = Number(row.vehicle?.current_km || 0)
  return Boolean((row.next_due_at && row.next_due_at < today) || (row.next_due_km && currentKm >= Number(row.next_due_km)))
}

function isMaintenanceSoon(row) {
  const today = new Date()
  const due = row.next_due_at ? new Date(`${row.next_due_at}T00:00:00`) : null
  const currentKm = Number(row.vehicle?.current_km || 0)
  return Boolean((due && due >= today && due <= new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000)) || (row.next_due_km && currentKm >= Number(row.next_due_km) - 500))
}

function formatMoney(value) {
  const amount = Number(value || 0)
  return amount ? amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '-'
}

function availabilityLabel(row) {
  if (row.status !== 'disponivel') return statusLabel(row.status)
  return row.schedule.length ? 'Reservado' : 'Livre'
}

function availabilityColor(row) {
  if (row.status === 'manutencao') return 'warning'
  if (row.status !== 'disponivel') return 'grey'
  return row.schedule.length ? 'negative' : 'positive'
}

function driverAvailabilityLabel(row) {
  if (row.status !== 'ativo') return statusLabel(row.status)
  if (isPastDate(row.cnh_expires_at)) return 'CNH vencida'
  return row.schedule.length ? 'Reservado' : 'Livre'
}

function driverAvailabilityColor(row) {
  if (row.status !== 'ativo') return 'grey'
  if (isPastDate(row.cnh_expires_at)) return 'negative'
  return row.schedule.length ? 'negative' : 'positive'
}

function isPastDate(value) {
  if (!value) return false

  const [date] = String(value).split('T')
  return date < new Date().toISOString().slice(0, 10)
}
</script>
