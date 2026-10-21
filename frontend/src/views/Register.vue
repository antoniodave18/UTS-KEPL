<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'

const router = useRouter()
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const error = ref('')

async function submit() {
  error.value = ''
  try {
    const { data } = await api.post('/register', form)
    localStorage.setItem('token', data.token)
    router.push('/tasks')
  } catch (e) {
    error.value = e.response?.data?.message || 'Register gagal'
  }
}
</script>

<template>
  <h2>Register</h2>
  <form @submit.prevent="submit">
    <input v-model="form.name" placeholder="Nama" required /><br />
    <input v-model="form.email" type="email" placeholder="Email" required /><br />
    <input v-model="form.password" type="password" placeholder="Password (min 6)" required /><br />
    <input v-model="form.password_confirmation" type="password" placeholder="Ulangi password" required /><br />
    <button type="submit">Daftar</button>
  </form>
  <p v-if="error" style="color: red">{{ error }}</p>
  <p>Sudah punya akun? <router-link to="/login">Login</router-link></p>
</template>