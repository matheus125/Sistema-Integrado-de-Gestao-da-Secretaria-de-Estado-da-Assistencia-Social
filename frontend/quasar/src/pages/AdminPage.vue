<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Administracao</div>
        <h1>Usuarios, setores e perfis</h1>
        <p>Controle acesso por setor, atribua perfis e ajuste permissoes do RBAC do SIGES.</p>
      </div>
      <div class="action-row">
        <q-btn v-if="canCreate" color="primary" icon="person_add" label="Novo usuario" unelevated @click="openUser()" />
        <q-btn v-if="canCreate" color="secondary" icon="apartment" label="Novo setor" unelevated @click="openDepartment()" />
        <q-btn v-if="canCreate" color="accent" icon="admin_panel_settings" label="Novo perfil" unelevated @click="openRole()" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-tabs v-model="tab" dense align="left" active-color="primary" indicator-color="primary">
        <q-tab name="users" icon="people" label="Usuarios" />
        <q-tab name="departments" icon="apartment" label="Setores" />
        <q-tab name="roles" icon="admin_panel_settings" label="Perfis" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated class="module-panels">
        <q-tab-panel name="users">
          <q-table flat bordered title="Usuarios" :rows="users" :columns="userColumns" row-key="id" :loading="loading">
            <template #top-right>
              <q-input v-model="search" dense outlined debounce="400" placeholder="Buscar" @update:model-value="loadAll">
                <template #prepend><q-icon name="search" /></template>
              </q-input>
            </template>
            <template #body-cell-status="props">
              <q-td :props="props">
                <q-badge :color="props.row.status === 'ativo' ? 'positive' : 'grey'" outline>{{ props.row.status }}</q-badge>
              </q-td>
            </template>
            <template #body-cell-roles="props">
              <q-td :props="props">{{ props.row.roles?.map(role => role.name).join(', ') || '-' }}</q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openUser(props.row)">
                  <q-tooltip>Editar usuario</q-tooltip>
                </q-btn>
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" @click="removeUser(props.row)">
                  <q-tooltip>Excluir usuario</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="departments">
          <q-table flat bordered title="Setores" :rows="departments" :columns="departmentColumns" row-key="id" :loading="loading">
            <template #body-cell-active="props">
              <q-td :props="props">
                <q-badge :color="props.row.active ? 'positive' : 'grey'" outline>{{ props.row.active ? 'ativo' : 'inativo' }}</q-badge>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openDepartment(props.row)" />
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" @click="removeDepartment(props.row)" />
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>

        <q-tab-panel name="roles">
          <q-table flat bordered title="Perfis" :rows="roles" :columns="roleColumns" row-key="id" :loading="loading">
            <template #body-cell-permissions="props">
              <q-td :props="props">{{ props.row.permissions?.length || 0 }} permissoes</q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn v-if="canEdit" flat round dense icon="edit" @click="openRole(props.row)" />
                <q-btn v-if="canDelete" flat round dense icon="delete" color="negative" @click="removeRole(props.row)" />
              </q-td>
            </template>
          </q-table>
        </q-tab-panel>
      </q-tab-panels>
    </section>

    <q-dialog v-model="userDialog">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ userForm.id ? 'Editar usuario' : 'Novo usuario' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveUser">
          <q-card-section class="form-grid">
            <q-input v-model="userForm.name" outlined label="Nome" :rules="[required]" />
            <q-input v-model="userForm.email" outlined type="email" label="Email" :rules="[required]" />
            <q-input v-model="userForm.password" outlined type="password" :label="userForm.id ? 'Nova senha' : 'Senha'" :rules="userForm.id ? [] : [required]" />
            <q-input v-model="userForm.phone" outlined label="Telefone" />
            <q-input v-model="userForm.position" outlined label="Cargo" />
            <q-select v-model="userForm.status" outlined label="Status" :options="userStatuses" emit-value map-options />
            <q-select v-model="userForm.department_id" outlined label="Setor" :options="departmentOptions" emit-value map-options clearable />
            <q-select v-model="userForm.roles" outlined label="Perfis" :options="roleOptions" multiple use-chips emit-value map-options />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="departmentDialog">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ departmentForm.id ? 'Editar setor' : 'Novo setor' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveDepartment">
          <q-card-section class="form-grid">
            <q-input v-model="departmentForm.name" outlined label="Nome" :rules="[required]" />
            <q-input v-model="departmentForm.slug" outlined label="Slug" />
            <q-select v-model="departmentForm.parent_id" outlined label="Setor pai" :options="departmentOptions" emit-value map-options clearable />
            <q-toggle v-model="departmentForm.active" label="Ativo" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="roleDialog">
      <q-card class="dialog-card permissions-dialog">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">{{ roleForm.id ? 'Editar perfil' : 'Novo perfil' }}</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-form @submit="saveRole">
          <q-card-section>
            <q-input v-model="roleForm.name" outlined label="Nome do perfil" :rules="[required]" />
            <div class="permission-grid q-mt-md">
              <div v-for="group in permissionGroups" :key="group.name" class="permission-box">
                <div class="text-weight-bold q-mb-sm">{{ group.name }}</div>
                <q-option-group v-model="roleForm.permissions" :options="group.options" type="checkbox" dense />
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn type="submit" color="primary" icon="save" label="Salvar" unelevated />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { apiFetch } from '@/services/api'
import { useAuthStore } from '@/stores/auth-store'

const $q = useQuasar()
const auth = useAuthStore()
const tab = ref('users')
const loading = ref(false)
const search = ref('')
const users = ref([])
const departments = ref([])
const roles = ref([])
const permissions = ref([])
const options = ref({ departments: [], roles: [] })
const userDialog = ref(false)
const departmentDialog = ref(false)
const roleDialog = ref(false)

const required = value => value !== null && value !== undefined && value !== '' || 'Campo obrigatorio'

const userStatuses = [
  { label: 'Ativo', value: 'ativo' },
  { label: 'Inativo', value: 'inativo' },
  { label: 'Afastado', value: 'afastado' }
]

const userForm = reactive(defaultUser())
const departmentForm = reactive(defaultDepartment())
const roleForm = reactive(defaultRole())

const userColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
  { name: 'email', label: 'Email', field: 'email', align: 'left' },
  { name: 'department', label: 'Setor', field: row => row.department?.name || '-', align: 'left' },
  { name: 'roles', label: 'Perfis', field: 'roles', align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const departmentColumns = [
  { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
  { name: 'slug', label: 'Slug', field: 'slug', align: 'left' },
  { name: 'parent', label: 'Setor pai', field: row => row.parent?.name || '-', align: 'left' },
  { name: 'children_count', label: 'Subsetores', field: 'children_count', align: 'right' },
  { name: 'active', label: 'Status', field: 'active', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const roleColumns = [
  { name: 'name', label: 'Perfil', field: 'name', align: 'left', sortable: true },
  { name: 'permissions', label: 'Permissoes', field: 'permissions', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const departmentOptions = computed(() => options.value.departments.map(item => ({ label: item.name, value: item.id })))
const roleOptions = computed(() => options.value.roles.map(item => ({ label: item, value: item })))
const canCreate = computed(() => auth.can('administracao.cadastrar'))
const canEdit = computed(() => auth.can('administracao.editar'))
const canDelete = computed(() => auth.can('administracao.excluir'))
const permissionGroups = computed(() => {
  const groups = {}
  for (const permission of permissions.value) {
    const [module, action] = permission.name.split('.')
    groups[module] ||= []
    groups[module].push({ label: action, value: permission.name })
  }
  return Object.entries(groups).map(([name, groupOptions]) => ({ name, options: groupOptions }))
})

onMounted(loadAll)

async function loadAll() {
  loading.value = true

  try {
    const [userData, departmentData, roleData, optionData] = await Promise.all([
      apiFetch(`/admin/users?search=${encodeURIComponent(search.value)}`),
      apiFetch(`/admin/departments?search=${encodeURIComponent(search.value)}`),
      apiFetch('/admin/roles'),
      apiFetch('/admin/options')
    ])

    users.value = userData.data || []
    departments.value = departmentData.data || []
    roles.value = roleData.roles || []
    permissions.value = roleData.permissions || []
    options.value = optionData
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

function defaultUser() {
  return { id: null, name: '', email: '', password: '', department_id: null, phone: '', position: '', status: 'ativo', roles: [] }
}

function defaultDepartment() {
  return { id: null, parent_id: null, name: '', slug: '', active: true }
}

function defaultRole() {
  return { id: null, name: '', permissions: [] }
}

function openUser(row = null) {
  Object.assign(userForm, row ? { ...defaultUser(), ...row, password: '', roles: row.roles?.map(role => role.name) || [] } : defaultUser())
  userDialog.value = true
}

function openDepartment(row = null) {
  Object.assign(departmentForm, row ? { ...defaultDepartment(), ...row } : defaultDepartment())
  departmentDialog.value = true
}

function openRole(row = null) {
  Object.assign(roleForm, row ? { ...defaultRole(), ...row, permissions: row.permissions?.map(permission => permission.name) || [] } : defaultRole())
  roleDialog.value = true
}

async function saveUser() {
  const payload = { ...userForm }
  if (payload.id && !payload.password) delete payload.password
  await save(payload.id ? `/admin/users/${payload.id}` : '/admin/users', payload.id ? 'PUT' : 'POST', payload, () => { userDialog.value = false })
}

async function saveDepartment() {
  await save(departmentForm.id ? `/admin/departments/${departmentForm.id}` : '/admin/departments', departmentForm.id ? 'PUT' : 'POST', departmentForm, () => { departmentDialog.value = false })
}

async function saveRole() {
  await save(roleForm.id ? `/admin/roles/${roleForm.id}` : '/admin/roles', roleForm.id ? 'PUT' : 'POST', roleForm, () => { roleDialog.value = false })
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

async function removeUser(row) {
  await remove(`/admin/users/${row.id}`, `Excluir o usuario ${row.name}?`)
}

async function removeDepartment(row) {
  await remove(`/admin/departments/${row.id}`, `Excluir o setor ${row.name}?`)
}

async function removeRole(row) {
  await remove(`/admin/roles/${row.id}`, `Excluir o perfil ${row.name}?`)
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
</script>
