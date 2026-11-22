<template>
  <div class="login-page">
    <section class="login-panel">
      <div class="login-copy">
        <div class="text-overline">SIGES</div>
        <h1>Gestao integrada da SEAS</h1>
        <p>Entre para acessar dashboards, solicitacoes, chamados, patrimonio, relatorios e auditoria.</p>
      </div>

      <q-card flat bordered class="login-card">
        <q-card-section>
          <div class="text-h6 text-weight-bold">Acessar sistema</div>
          <div class="text-caption text-grey-7">Entre com suas credenciais de acesso.</div>
        </q-card-section>

        <q-form @submit="submit">
          <q-card-section class="q-gutter-md">
            <q-input
              v-model="form.email"
              v-bind="serverErrorProps('email')"
              label="Email"
              type="email"
              autocomplete="email"
              outlined
              :maxlength="180"
              :rules="[value => !!value || 'Informe o email', maxLength(180)]"
            />
            <q-input
              v-model="form.password"
              v-bind="serverErrorProps('password')"
              label="Senha"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              outlined
              :maxlength="255"
              :rules="[value => !!value || 'Informe a senha', maxLength(255)]"
            >
              <template #append>
                <q-btn
                  flat
                  dense
                  round
                  :icon="showPassword ? 'visibility_off' : 'visibility'"
                  :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                  @click="showPassword = !showPassword"
                >
                  <q-tooltip>{{ showPassword ? 'Ocultar senha' : 'Mostrar senha' }}</q-tooltip>
                </q-btn>
              </template>
            </q-input>
          </q-card-section>

          <q-card-section class="q-pt-none">
            <q-btn
              type="submit"
              color="primary"
              icon="login"
              label="Entrar"
              class="full-width"
              unelevated
              :loading="auth.loading"
            />
          </q-card-section>
        </q-form>

      </q-card>
    </section>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth-store'
import { serverErrorProps } from '@/services/server-validation'
import { maxLength } from '@/utils/form-validation'

const $q = useQuasar()
const router = useRouter()
const auth = useAuthStore()
const showPassword = ref(false)

const form = reactive({
  email: '',
  password: ''
})

async function submit() {
  try {
    await auth.login(form)
    $q.notify({ type: 'positive', message: 'Login realizado com sucesso.' })
    router.push('/')
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}
</script>
