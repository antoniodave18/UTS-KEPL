<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'

const router = useRouter()
const form = reactive({ email: '', password: '' })
const error = ref('')

async function submit() {
  error.value = ''
  try {
    const { data } = await api.post('/login', form)
    localStorage.setItem('token', data.token)
    router.push('/tasks')
  } catch (e) {
    error.value = e.response?.data?.message || 'Login gagal'
  }
}
</script>

<template>
  <h2>Login</h2>
  <form @submit.prevent="submit">
    <input v-model="form.email" type="email" placeholder="Email" required /><br />
    <input v-model="form.password" type="password" placeholder="Password" required /><br />
    <button type="submit">Login</button>
  </form>
  <p v-if="error" style="color: red">{{ error }}</p>
  <p>Belum punya akun? <router-link to="/register">Register</router-link></p>
</template>