<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'

const router = useRouter()
const tasks = ref([])
const title = ref('')

async function load() {
  const { data } = await api.get('/tasks')
  tasks.value = data
}

async function add() {
  if (!title.value.trim()) return
  await api.post('/tasks', { title: title.value })
  title.value = ''
  load()
}

async function toggle(t) {
  await api.put(`/tasks/${t.id}`, { is_done: !t.is_done })
  load()
}

async function edit(t) {
  const newTitle = prompt('Ubah judul task:', t.title)
  if (newTitle && newTitle.trim()) {
    await api.put(`/tasks/${t.id}`, { title: newTitle })
    load()
  }
}

async function remove(t) {
  await api.delete(`/tasks/${t.id}`)
  load()
}

async function logout() {
  try { await api.post('/logout') } catch (_) { /* abaikan */ }
  localStorage.removeItem('token')
  router.push('/login')
}

onMounted(load)
</script>

<template>
  <div style="display: flex; justify-content: space-between">
    <h2>To do List</h2>
    <button @click="logout">Logout</button>
  </div>

  <form @submit.prevent="add">
    <input v-model="title" placeholder="Task baru..." />
    <button type="submit">Tambah</button>
  </form>

  <ul>
    <li v-for="t in tasks" :key="t.id">
      <input type="checkbox" :checked="t.is_done" @change="toggle(t)" />
      <span :style="{ textDecoration: t.is_done ? 'line-through' : 'none' }">{{ t.title }}</span>
      <button @click="edit(t)">Edit</button>
      <button @click="remove(t)">Hapus</button>
    </li>
  </ul>
  <p v-if="!tasks.length">Belum ada task.</p>
</template>