<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Gerencia de Materiais e Patrimonio</div>
        <h1>Estoque e bens patrimoniais</h1>
        <p>Controle produtos, entradas, saidas, patrimonio e transferencias entre setores.</p>
      </div>
      <div class="action-row">
        <q-btn v-if="canCreate" color="primary" icon="inventory_2" label="Novo produto" unelevated @click="openProduct()" />
        <q-btn v-if="canCreate" color="secondary" icon="sync_alt" label="Movimentar estoque" unelevated @click="openStockMovement()" />
        <q-btn v-if="canCreate" color="accent" icon="business_center" label="Novo patrimonio" unelevated @click="openAsset()" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-tabs v-model="tab" dense align="left" active-color="primary" indicator-color="primary">
        <q-tab name="products" icon="inventory_2" label="Produtos" />
        <q-tab name="stock" icon="sync_alt" label="Estoque" />
        <q-tab name="assets" icon="business_center" label="Patrimonio" />
        <q-tab name="assetMovements" icon="swap_horiz" label="Movimentacoes" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated class="module-panels">
        <q-tab-panel name="products">
          <q-table
            flat
            bordered
            title="Produtos"
            :rows="products"
            :columns="productColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhum produto cadastrado"
          >
            <template #top-right>
              <q-input v-model="search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadAll">
                <template #prepend><q-icon name="search" /></template>
              </q-input>
            </template>
            <template #body-cell-quantity="props">
              <q-td :props="props">
                <q-badge :color="isLowStock(props.row) ? 'negative' : 'positive'" outline>
                  {{ formatNumber(props.row.quantity) }} {{ props.row.unit }}
                </q-badge>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" aria-label="Editar produto" @click="openProduct(props.row)">
                  <q-tooltip>Editar produto</q-tooltip>
                </q-btn>
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" aria-label="Excluir produto" @click="removeProduct(props.row)">
                  <q-tooltip>Excluir produto</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="stock">
          <q-table
            flat
            bordered
            title="Movimentacoes de estoque"
            :rows="stockMovements"
            :columns="stockColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhuma movimentacao registrada"
          >
            <template #top-right>
              <q-btn v-if="canCreate" color="primary" icon="add" label="Nova movimentacao" unelevated @click="openStockMovement()" />
            </template>
            <template #body-cell-type="props">
              <q-td :props="props">
                <q-badge :color="props.row.type === 'entrada' ? 'positive' : 'warning'" outline>
                  {{ props.row.type }}
                </q-badge>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="assets">
          <q-table
            flat
            bordered
            title="Patrimonios"
            :rows="assets"
            :columns="assetColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhum patrimonio cadastrado"
          >
            <template #top-right>
              <q-btn v-if="canCreate" color="primary" icon="add" label="Novo patrimonio" unelevated @click="openAsset()" />
            </template>
            <template #body-cell-status="props">
              <q-td :props="props">
                <q-badge :color="assetStatusColor(props.row.status)" outline>{{ props.row.status }}</q-badge>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="swap_horiz" aria-label="Movimentar patrimônio" @click="openAssetMovement(props.row)">
                  <q-tooltip>Movimentar patrimonio</q-tooltip>
                </q-btn>
                <q-btn v-if="canEdit" flat round dense icon="edit" aria-label="Editar patrimônio" @click="openAsset(props.row)">
                  <q-tooltip>Editar patrimonio</q-tooltip>
                </q-btn>
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" aria-label="Excluir patrimônio" @click="removeAsset(props.row)">
                  <q-tooltip>Excluir patrimonio</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="assetMovements">
          <q-table
            flat
            bordered
            title="Movimentacoes de patrimonio"
            :rows="assetMovements"
            :columns="assetMovementColumns"
            row-key="id"
            :loading="loading"
            no-data-label="Nenhuma movimentacao registrada"
          >
            <template #top-right>
              <q-btn v-if="canCreate" color="primary" icon="add" label="Nova movimentacao" unelevated @click="openAssetMovement()" />
            </template>
          </q-table>
        </q-tab-panel>
      </q-tab-panels>
    </section>

    <q-dialog v-model="productDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ productForm.id ? 'Editar produto' : 'Novo produto' }}</div>
          <q-btn flat round dense icon="close" aria-label="Fechar produto" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveProduct">
          <q-card-section class="form-grid">
            <q-input v-model="productForm.internal_code" v-bind="serverErrorProps('internal_code')" outlined label="Codigo interno" :maxlength="80" :rules="[required, maxLength(80)]" />
            <q-input v-model="productForm.description" v-bind="serverErrorProps('description')" outlined label="Descricao" :maxlength="255" :rules="[required, maxLength(255)]" />
            <q-input v-model="productForm.unit" v-bind="serverErrorProps('unit')" outlined label="Unidade" :maxlength="30" :rules="[required, maxLength(30)]" />
            <q-input v-model="productForm.supplier" v-bind="serverErrorProps('supplier')" outlined label="Fornecedor" :maxlength="160" :rules="[maxLength(160)]" />
            <q-input v-model.number="productForm.quantity" v-bind="serverErrorProps('quantity')" outlined type="number" step="0.01" label="Quantidade" />
            <q-input v-model.number="productForm.minimum_quantity" v-bind="serverErrorProps('minimum_quantity')" outlined type="number" step="0.01" label="Quantidade minima" />
            <q-input v-model="productForm.location" v-bind="serverErrorProps('location')" outlined label="Localizacao" :maxlength="160" :rules="[maxLength(160)]" />
            <q-select v-model="productForm.status" v-bind="serverErrorProps('status')" outlined label="Situacao" :options="productStatuses" emit-value map-options />
            <div v-if="productForm.id" class="attachment-panel span-2">
              <div class="attachment-header">
                <div>
                  <div class="text-subtitle2">Anexos</div>
                  <div class="text-caption text-grey-7">{{ productAttachments.length }} arquivo(s) vinculados</div>
                </div>
                <q-file
                  v-if="canEdit"
                  v-model="productAttachmentFile"
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
                  v-if="canEdit"
                  color="primary"
                  icon="upload"
                  round
                  unelevated
                  :disable="!productAttachmentFile"
                  :loading="uploadingProductAttachment"
              aria-label="Enviar anexo do produto"
              @click.prevent="uploadProductAttachment"
                >
                  <q-tooltip>Enviar anexo</q-tooltip>
                </q-btn>
              </div>

              <q-list v-if="productAttachments.length" bordered separator class="attachment-list">
                <q-item v-for="attachment in productAttachments" :key="attachment.id">
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
                      <q-btn flat round dense icon="download" aria-label="Baixar anexo do produto" @click.prevent="downloadAttachment(attachment)">
                        <q-tooltip>Baixar anexo</q-tooltip>
                      </q-btn>
                      <q-btn v-if="canEdit" flat round dense icon="delete" color="negative" aria-label="Remover anexo do produto" @click.prevent="removeProductAttachment(attachment)">
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

    <q-dialog v-model="stockDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">Movimentar estoque</div>
          <q-btn flat round dense icon="close" aria-label="Fechar movimentação de estoque" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveStockMovement">
          <q-card-section class="form-grid">
            <q-select v-model="stockForm.product_id" v-bind="serverErrorProps('product_id')" outlined label="Produto" :options="productOptions" emit-value map-options :rules="[required]" class="span-2" />
            <q-select v-model="stockForm.type" v-bind="serverErrorProps('type')" outlined label="Tipo" :options="stockTypes" emit-value map-options />
            <q-input v-model.number="stockForm.quantity" v-bind="serverErrorProps('quantity')" outlined type="number" step="0.01" label="Quantidade" :rules="[required]" />
            <q-input v-model="stockForm.movement_date" v-bind="serverErrorProps('movement_date')" outlined type="date" label="Data" :rules="[required]" />
            <q-select v-model="stockForm.department_id" v-bind="serverErrorProps('department_id')" outlined label="Setor" :options="departmentOptions" emit-value map-options clearable />
            <q-input v-model="stockForm.supplier" v-bind="serverErrorProps('supplier')" outlined label="Fornecedor" :maxlength="160" :rules="[maxLength(160)]" />
            <q-input v-model="stockForm.invoice_number" v-bind="serverErrorProps('invoice_number')" outlined label="Nota fiscal" :maxlength="80" :rules="[maxLength(80)]" />
            <q-input v-model="stockForm.destination" v-bind="serverErrorProps('destination')" outlined label="Destino" :maxlength="160" :rules="[maxLength(160)]" />
            <q-input v-model="stockForm.reason" v-bind="serverErrorProps('reason')" outlined label="Motivo" :maxlength="160" :rules="[maxLength(160)]" />
            <q-input v-model="stockForm.notes" v-bind="serverErrorProps('notes')" outlined type="textarea" label="Observacoes" :maxlength="4000" counter :rules="[maxLength(4000)]" class="span-2" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Registrar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="assetDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ assetForm.id ? 'Editar patrimonio' : 'Novo patrimonio' }}</div>
          <q-btn flat round dense icon="close" aria-label="Fechar patrimônio" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveAsset">
          <q-card-section class="form-grid">
            <q-input v-model="assetForm.asset_number" v-bind="serverErrorProps('asset_number')" outlined label="Numero patrimonial" :maxlength="80" :rules="[required, maxLength(80)]" />
            <q-input v-model="assetForm.description" v-bind="serverErrorProps('description')" outlined label="Descricao" :maxlength="255" :rules="[required, maxLength(255)]" />
            <q-input v-model="assetForm.category" v-bind="serverErrorProps('category')" outlined label="Categoria" :maxlength="120" :rules="[maxLength(120)]" />
            <q-input v-model="assetForm.brand" v-bind="serverErrorProps('brand')" outlined label="Marca" :maxlength="120" :rules="[maxLength(120)]" />
            <q-input v-model="assetForm.model" v-bind="serverErrorProps('model')" outlined label="Modelo" :maxlength="120" :rules="[maxLength(120)]" />
            <q-input v-model="assetForm.serial_number" v-bind="serverErrorProps('serial_number')" outlined label="Numero de serie" :maxlength="120" :rules="[maxLength(120)]" />
            <q-input v-model="assetForm.acquired_at" v-bind="serverErrorProps('acquired_at')" outlined type="date" label="Data da aquisicao" />
            <BrlCurrencyInput v-model="assetForm.value" v-bind="serverErrorProps('value')" outlined label="Valor" :max-integer-digits="10" />
            <q-input v-model="assetForm.invoice_number" v-bind="serverErrorProps('invoice_number')" outlined label="Nota fiscal" :maxlength="80" :rules="[maxLength(80)]" />
            <q-input v-model="assetForm.location" v-bind="serverErrorProps('location')" outlined label="Localizacao" :maxlength="160" :rules="[maxLength(160)]" />
            <q-select v-model="assetForm.department_id" v-bind="serverErrorProps('department_id')" outlined label="Setor" :options="departmentOptions" emit-value map-options clearable />
            <q-select v-model="assetForm.responsible_id" v-bind="serverErrorProps('responsible_id')" outlined label="Responsavel" :options="userOptions" emit-value map-options clearable />
            <q-select v-model="assetForm.status" v-bind="serverErrorProps('status')" outlined label="Situacao" :options="assetStatuses" emit-value map-options />
            <div v-if="assetForm.id" class="attachment-panel span-2">
              <div class="attachment-header">
                <div>
                  <div class="text-subtitle2">Anexos</div>
                  <div class="text-caption text-grey-7">{{ assetAttachments.length }} arquivo(s) vinculados</div>
                </div>
                <q-file
                  v-if="canEdit"
                  v-model="assetAttachmentFile"
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
                  v-if="canEdit"
                  color="primary"
                  icon="upload"
                  round
                  unelevated
                  :disable="!assetAttachmentFile"
                  :loading="uploadingAssetAttachment"
              aria-label="Enviar anexo do patrimônio"
              @click.prevent="uploadAssetAttachment"
                >
                  <q-tooltip>Enviar anexo</q-tooltip>
                </q-btn>
              </div>

              <q-list v-if="assetAttachments.length" bordered separator class="attachment-list">
                <q-item v-for="attachment in assetAttachments" :key="attachment.id">
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
                      <q-btn flat round dense icon="download" aria-label="Baixar anexo do patrimônio" @click.prevent="downloadAttachment(attachment)">
                        <q-tooltip>Baixar anexo</q-tooltip>
                      </q-btn>
                      <q-btn v-if="canEdit" flat round dense icon="delete" color="negative" aria-label="Remover anexo do patrimônio" @click.prevent="removeAssetAttachment(attachment)">
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

    <q-dialog v-model="assetMovementDialog" @before-show="clearServerValidationErrors">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">Movimentar patrimonio</div>
          <q-btn flat round dense icon="close" aria-label="Fechar movimentação de patrimônio" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveAssetMovement">
          <q-card-section class="form-grid">
            <q-select v-model="assetMovementForm.asset_id" v-bind="serverErrorProps('asset_id')" outlined label="Patrimonio" :options="assetOptions" emit-value map-options :rules="[required]" class="span-2" />
            <q-select v-model="assetMovementForm.type" v-bind="serverErrorProps('type')" outlined label="Tipo" :options="assetMovementTypes" emit-value map-options />
            <q-select v-model="assetMovementForm.to_department_id" v-bind="serverErrorProps('to_department_id')" outlined label="Setor destino" :options="departmentOptions" emit-value map-options clearable />
            <q-input v-model="assetMovementForm.moved_at" v-bind="serverErrorProps('moved_at')" outlined type="datetime-local" label="Data/hora" :rules="[required]" class="span-2" />
            <q-input v-model="assetMovementForm.notes" v-bind="serverErrorProps('notes')" outlined type="textarea" label="Observacoes" :maxlength="4000" counter :rules="[maxLength(4000)]" class="span-2" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Registrar" unelevated />
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
import { clearServerValidationErrors, serverErrorProps } from '@/services/server-validation'
import { useAuthStore } from '@/stores/auth-store'
import { formatDate, formatDateTime } from '@/composables/useDateFormat'
import { maxLength } from '@/utils/form-validation'
import BrlCurrencyInput from '@/components/BrlCurrencyInput.vue'

const $q = useQuasar()
const auth = useAuthStore()
const tab = ref('products')
const loading = ref(false)
const search = ref('')
const products = ref([])
const stockMovements = ref([])
const assets = ref([])
const assetMovements = ref([])
const options = ref({ departments: [], users: [], products: [], assets: [] })
const productDialog = ref(false)
const stockDialog = ref(false)
const assetDialog = ref(false)
const assetMovementDialog = ref(false)
const productAttachments = ref([])
const productAttachmentFile = ref(null)
const uploadingProductAttachment = ref(false)
const assetAttachments = ref([])
const assetAttachmentFile = ref(null)
const uploadingAssetAttachment = ref(false)

const required = value => value !== null && value !== undefined && value !== '' || 'Campo obrigatorio'

const productStatuses = [
  { label: 'Ativo', value: 'ativo' },
  { label: 'Inativo', value: 'inativo' }
]

const stockTypes = [
  { label: 'Entrada', value: 'entrada' },
  { label: 'Saida', value: 'saida' }
]

const assetStatuses = [
  { label: 'Ativo', value: 'ativo' },
  { label: 'Manutencao', value: 'manutencao' },
  { label: 'Baixado', value: 'baixado' },
  { label: 'Inativo', value: 'inativo' }
]

const assetMovementTypes = [
  { label: 'Transferencia', value: 'transferencia' },
  { label: 'Manutencao', value: 'manutencao' },
  { label: 'Retorno', value: 'retorno' },
  { label: 'Baixa', value: 'baixa' }
]

const productForm = reactive(defaultProduct())
const stockForm = reactive(defaultStockMovement())
const assetForm = reactive(defaultAsset())
const assetMovementForm = reactive(defaultAssetMovement())

const productColumns = [
  { name: 'internal_code', label: 'Codigo', field: 'internal_code', align: 'left', sortable: true },
  { name: 'description', label: 'Descricao', field: 'description', align: 'left' },
  { name: 'quantity', label: 'Estoque', field: 'quantity', align: 'left' },
  { name: 'minimum_quantity', label: 'Minimo', field: row => formatNumber(row.minimum_quantity), align: 'right' },
  { name: 'location', label: 'Localizacao', field: 'location', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const stockColumns = [
  { name: 'movement_date', label: 'Data', field: row => formatDate(row.movement_date), align: 'left' },
  { name: 'type', label: 'Tipo', field: 'type', align: 'left' },
  { name: 'product', label: 'Produto', field: row => row.product?.description, align: 'left' },
  { name: 'quantity', label: 'Quantidade', field: row => formatNumber(row.quantity), align: 'right' },
  { name: 'department', label: 'Setor', field: row => row.department?.name || '-', align: 'left' },
  { name: 'responsible', label: 'Responsavel', field: row => row.responsible?.name || '-', align: 'left' }
]

const assetColumns = [
  { name: 'asset_number', label: 'Numero', field: 'asset_number', align: 'left', sortable: true },
  { name: 'description', label: 'Descricao', field: 'description', align: 'left' },
  { name: 'department', label: 'Setor', field: row => row.department?.name || '-', align: 'left' },
  { name: 'location', label: 'Localizacao', field: 'location', align: 'left' },
  { name: 'status', label: 'Situacao', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const assetMovementColumns = [
  { name: 'moved_at', label: 'Data/hora', field: row => formatDateTime(row.moved_at), align: 'left' },
  { name: 'type', label: 'Tipo', field: 'type', align: 'left' },
  { name: 'asset', label: 'Patrimonio', field: row => row.asset?.description, align: 'left' },
  { name: 'from', label: 'Origem', field: row => row.from_department?.name || row.fromDepartment?.name || '-', align: 'left' },
  { name: 'to', label: 'Destino', field: row => row.to_department?.name || row.toDepartment?.name || '-', align: 'left' },
  { name: 'responsible', label: 'Responsavel', field: row => row.responsible?.name || '-', align: 'left' }
]

const departmentOptions = computed(() => options.value.departments.map(item => ({ label: item.name, value: item.id })))
const userOptions = computed(() => options.value.users.map(item => ({ label: item.name, value: item.id })))
const productOptions = computed(() => options.value.products.map(item => ({ label: `${item.internal_code} - ${item.description} (${formatNumber(item.quantity)})`, value: item.id })))
const assetOptions = computed(() => options.value.assets.map(item => ({ label: `${item.asset_number} - ${item.description}`, value: item.id })))
const canCreate = computed(() => auth.can('materiais-patrimonio.cadastrar'))
const canEdit = computed(() => auth.can('materiais-patrimonio.editar'))
const canDelete = computed(() => auth.can('materiais-patrimonio.excluir'))

onMounted(loadAll)

async function loadAll() {
  loading.value = true

  try {
    const [productData, stockData, assetData, assetMovementData, optionData] = await Promise.all([
      apiFetch(`/materials/products?search=${encodeURIComponent(search.value)}`),
      apiFetch('/materials/stock-movements'),
      apiFetch(`/materials/assets?search=${encodeURIComponent(search.value)}`),
      apiFetch('/materials/asset-movements'),
      apiFetch('/materials/options')
    ])

    products.value = productData.data || []
    stockMovements.value = stockData.data || []
    assets.value = assetData.data || []
    assetMovements.value = assetMovementData.data || []
    options.value = optionData
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

function defaultProduct() {
  return { id: null, category_id: null, internal_code: '', description: '', unit: 'un', supplier: '', quantity: 0, minimum_quantity: 0, location: '', status: 'ativo' }
}

function defaultStockMovement() {
  return { product_id: null, department_id: null, type: 'entrada', quantity: 1, supplier: '', invoice_number: '', destination: '', reason: '', movement_date: today(), notes: '' }
}

function defaultAsset() {
  return { id: null, department_id: null, responsible_id: null, asset_number: '', description: '', category: '', brand: '', model: '', serial_number: '', acquired_at: '', value: null, invoice_number: '', location: '', status: 'ativo' }
}

function defaultAssetMovement() {
  return { asset_id: null, to_department_id: null, type: 'transferencia', moved_at: new Date().toISOString().slice(0, 16), notes: '' }
}

function assignForm(target, data) {
  Object.assign(target, data)
}

async function openProduct(row = null) {
  assignForm(productForm, row ? { ...defaultProduct(), ...row } : defaultProduct())
  productAttachmentFile.value = null
  productAttachments.value = []
  if (row?.id) {
    await loadProductAttachments(row.id)
  }
  productDialog.value = true
}

function openStockMovement() {
  assignForm(stockForm, defaultStockMovement())
  stockDialog.value = true
}

async function openAsset(row = null) {
  assignForm(assetForm, row ? { ...defaultAsset(), ...row } : defaultAsset())
  assetAttachmentFile.value = null
  assetAttachments.value = []
  if (row?.id) {
    await loadAssetAttachments(row.id)
  }
  assetDialog.value = true
}

function openAssetMovement(row = null) {
  assignForm(assetMovementForm, { ...defaultAssetMovement(), asset_id: row?.id || null, to_department_id: row?.department_id || null })
  assetMovementDialog.value = true
}

async function saveProduct() {
  const method = productForm.id ? 'PUT' : 'POST'
  const path = productForm.id ? `/materials/products/${productForm.id}` : '/materials/products'
  await save(path, method, productForm, () => { productDialog.value = false })
}

async function saveStockMovement() {
  await save('/materials/stock-movements', 'POST', stockForm, () => {
    stockDialog.value = false
    tab.value = 'stock'
  })
}

async function saveAsset() {
  const method = assetForm.id ? 'PUT' : 'POST'
  const path = assetForm.id ? `/materials/assets/${assetForm.id}` : '/materials/assets'
  await save(path, method, assetForm, () => { assetDialog.value = false })
}

async function saveAssetMovement() {
  await save('/materials/asset-movements', 'POST', assetMovementForm, () => {
    assetMovementDialog.value = false
    tab.value = 'assetMovements'
  })
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

async function removeProduct(row) {
  await remove(`/materials/products/${row.id}`, `Excluir o produto ${row.description}?`)
}

async function removeAsset(row) {
  await remove(`/materials/assets/${row.id}`, `Excluir o patrimonio ${row.asset_number}?`)
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

async function loadProductAttachments(id) {
  const payload = await apiFetch(`/attachments/product/${id}`)
  productAttachments.value = payload.data || []
}

async function loadAssetAttachments(id) {
  const payload = await apiFetch(`/attachments/asset/${id}`)
  assetAttachments.value = payload.data || []
}

async function uploadProductAttachment() {
  if (!productForm.id || !productAttachmentFile.value) return

  uploadingProductAttachment.value = true

  try {
    const formData = new FormData()
    formData.append('file', productAttachmentFile.value)
    await apiUpload(`/attachments/product/${productForm.id}`, formData)
    productAttachmentFile.value = null
    await loadProductAttachments(productForm.id)
    $q.notify({ type: 'positive', message: 'Anexo enviado.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    uploadingProductAttachment.value = false
  }
}

async function uploadAssetAttachment() {
  if (!assetForm.id || !assetAttachmentFile.value) return

  uploadingAssetAttachment.value = true

  try {
    const formData = new FormData()
    formData.append('file', assetAttachmentFile.value)
    await apiUpload(`/attachments/asset/${assetForm.id}`, formData)
    assetAttachmentFile.value = null
    await loadAssetAttachments(assetForm.id)
    $q.notify({ type: 'positive', message: 'Anexo enviado.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    uploadingAssetAttachment.value = false
  }
}

async function downloadAttachment(attachment) {
  try {
    await apiDownload(attachment.download_url, attachment.original_name)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function removeProductAttachment(attachment) {
  removeAttachment(attachment, () => loadProductAttachments(productForm.id))
}

function removeAssetAttachment(attachment) {
  removeAttachment(attachment, () => loadAssetAttachments(assetForm.id))
}

function removeAttachment(attachment, afterRemove) {
  $q.dialog({
    title: 'Remover anexo',
    message: `Remover ${attachment.original_name}?`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await apiFetch(`/attachments/${attachment.id}`, { method: 'DELETE' })
      await afterRemove()
      $q.notify({ type: 'positive', message: 'Anexo removido.' })
    } catch (error) {
      $q.notify({ type: 'negative', message: error.message })
    }
  })
}

function isLowStock(row) {
  return Number(row.quantity || 0) <= Number(row.minimum_quantity || 0)
}

function assetStatusColor(status) {
  return {
    ativo: 'positive',
    manutencao: 'warning',
    baixado: 'negative',
    inativo: 'grey'
  }[status] || 'grey'
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })
}

function formatFileSize(size) {
  const bytes = Number(size || 0)
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

function today() {
  return new Date().toISOString().slice(0, 10)
}
</script>
