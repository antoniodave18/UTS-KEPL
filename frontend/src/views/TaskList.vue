<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'

const router = useRouter()
const days = ref([])
const tasks = ref([])
const selectedDayId = ref(null)
const selectedDate = ref('')
const title = ref('')
const editingId = ref(null)
const editedTitle = ref('')
const editError = ref('')
const pageError = ref('')
const savingEdit = ref(false)

const completedCount = computed(() => tasks.value.filter((task) => task.is_done).length)
const pendingCount = computed(() => tasks.value.length - completedCount.value)
const progress = computed(() => tasks.value.length
  ? Math.round((completedCount.value / tasks.value.length) * 100)
  : 0)
const selectedDay = computed(() => days.value.find((day) => day.id === selectedDayId.value))
const selectedDateLabel = computed(() => selectedDay.value
  ? formatDate(selectedDay.value.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  : '')

function localDateString(date = new Date()) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function formatDate(date, options) {
  const [year, month, day] = date.slice(0, 10).split('-').map(Number)
  return new Intl.DateTimeFormat('id-ID', options).format(new Date(year, month - 1, day))
}

function errorMessage(error, fallback) {
  return error.response?.data?.message || fallback
}

async function loadTasks() {
  if (!selectedDayId.value) {
    tasks.value = []
    return
  }
  const { data } = await api.get('/tasks', { params: { day_id: selectedDayId.value } })
  tasks.value = data
}

async function load() {
  pageError.value = ''
  try {
    const { data } = await api.get('/days')
    days.value = data

    const today = localDateString()
    let day = days.value.find((item) => item.date === today)
    if (!day) {
      const { data: createdDay } = await api.post('/days', { date: today })
      day = createdDay
      days.value = [day, ...days.value].sort((left, right) => right.date.localeCompare(left.date))
    }

    selectedDayId.value = days.value.some((item) => item.id === selectedDayId.value)
      ? selectedDayId.value
      : day.id
    selectedDate.value = today
    await loadTasks()
  } catch (error) {
    pageError.value = errorMessage(error, 'Daftar hari dan tugas gagal dimuat.')
  }
}

async function selectDay(day) {
  selectedDayId.value = day.id
  pageError.value = ''
  try {
    await loadTasks()
  } catch (error) {
    pageError.value = errorMessage(error, 'Tugas untuk hari ini gagal dimuat.')
  }
}

async function addDay() {
  if (!selectedDate.value) return
  pageError.value = ''
  try {
    const { data } = await api.post('/days', { date: selectedDate.value })
    const existingDay = days.value.find((day) => day.id === data.id)
    if (!existingDay) {
      days.value = [...days.value, data].sort((left, right) => right.date.localeCompare(left.date))
    }
    await selectDay(data)
  } catch (error) {
    pageError.value = errorMessage(error, 'Hari baru gagal ditambahkan.')
  }
}

async function add() {
  const newTitle = title.value.trim()
  if (!newTitle || !selectedDayId.value) return
  pageError.value = ''
  try {
    const { data } = await api.post('/tasks', {
      title: newTitle,
      task_day_id: selectedDayId.value,
    })
    tasks.value = [data, ...tasks.value]
    title.value = ''
    const day = days.value.find((item) => item.id === selectedDayId.value)
    if (day) day.tasks_count = (day.tasks_count || 0) + 1
  } catch (error) {
    pageError.value = errorMessage(error, 'Tugas gagal ditambahkan.')
  }
}

async function toggle(task) {
  pageError.value = ''
  try {
    const { data } = await api.put(`/tasks/${task.id}`, { is_done: !task.is_done })
    const index = tasks.value.findIndex((item) => item.id === task.id)
    if (index !== -1) tasks.value[index] = data
  } catch (error) {
    pageError.value = errorMessage(error, 'Status tugas gagal diperbarui.')
  }
}

function startEdit(task) {
  editingId.value = task.id
  editedTitle.value = task.title
  editError.value = ''
}

function cancelEdit() {
  editingId.value = null
  editedTitle.value = ''
  editError.value = ''
}

async function saveEdit(task) {
  const newTitle = editedTitle.value.trim()
  if (!newTitle) {
    editError.value = 'Judul tugas tidak boleh kosong.'
    return
  }

  savingEdit.value = true
  editError.value = ''
  try {
    const { data } = await api.put(`/tasks/${task.id}`, { title: newTitle })
    const index = tasks.value.findIndex((item) => item.id === task.id)
    if (index !== -1) tasks.value[index] = data
    cancelEdit()
  } catch (error) {
    editError.value = errorMessage(error, 'Perubahan tugas gagal disimpan.')
  } finally {
    savingEdit.value = false
  }
}

async function remove(task) {
  pageError.value = ''
  try {
    await api.delete(`/tasks/${task.id}`)
    tasks.value = tasks.value.filter((item) => item.id !== task.id)
    const day = days.value.find((item) => item.id === selectedDayId.value)
    if (day) day.tasks_count = Math.max(0, (day.tasks_count || 0) - 1)
  } catch (error) {
    pageError.value = errorMessage(error, 'Tugas gagal dihapus.')
  }
}

async function logout() {
  try {
    await api.post('/logout')
  } finally {
    localStorage.removeItem('token')
    router.push('/login')
  }
}

onMounted(() => {
  load()
  window.addEventListener('focus', load)
})

onBeforeUnmount(() => {
  window.removeEventListener('focus', load)
})
</script>

<template>
  <div class="task-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">CATATAN HARIAN</p>
        <h1>Daftar tugas</h1>
      </div>
      <button class="logout-button" type="button" @click="logout">Keluar</button>
    </header>

    <section class="day-section" aria-label="Pilih hari">
      <div class="day-heading">
        <div>
          <h2>Hari</h2>
          <p class="muted-copy">Pilih atau tambahkan hari untuk mengatur tugas.</p>
        </div>
      </div>

      <div class="day-controls">
        <div class="day-tabs" role="tablist" aria-label="Hari yang tersedia">
          <button
            v-for="day in days"
            :key="day.id"
            class="day-tab"
            :class="{ 'is-active': day.id === selectedDayId }"
            type="button"
            role="tab"
            :aria-selected="day.id === selectedDayId"
            @click="selectDay(day)"
          >
            <span>{{ formatDate(day.date, { weekday: 'short' }) }}</span>
            <strong>{{ formatDate(day.date, { day: 'numeric', month: 'short' }) }}</strong>
          </button>
        </div>

        <form class="add-day-form" @submit.prevent="addDay">
          <label class="visually-hidden" for="new-day">Tanggal baru</label>
          <input id="new-day" v-model="selectedDate" type="date" />
          <button type="submit" aria-label="Tambahkan hari" title="Tambahkan hari">+</button>
        </form>
      </div>
    </section>

    <p v-if="pageError" class="page-error" role="alert">{{ pageError }}</p>

    <section class="task-panel" aria-label="Daftar tugas harian">
      <div class="panel-heading">
        <div>
          <p class="eyebrow">RENCANA HARIAN</p>
          <h2>{{ selectedDateLabel || 'Memuat hari...' }}</h2>
        </div>
        <span class="task-total">{{ tasks.length }} tugas</span>
      </div>

      <form class="task-form" @submit.prevent="add">
        <label class="visually-hidden" for="new-task">Tulis tugas</label>
        <input
          id="new-task"
          v-model="title"
          :disabled="!selectedDayId"
          placeholder="Tulis tugas..."
        />
        <button class="add-button" type="submit" :disabled="!selectedDayId || !title.trim()">Tambah tugas</button>
      </form>

      <section class="progress-section" aria-label="Progres hari ini">
        <div class="progress-heading">
          <span>Progres hari ini</span>
          <span>{{ completedCount }}/{{ tasks.length }} selesai <strong>{{ progress }}%</strong></span>
        </div>
        <div
          class="progress-track"
          role="progressbar"
          :aria-valuenow="progress"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="`${progress}% tugas selesai`"
        >
          <span class="progress-fill" :style="{ width: `${progress}%` }"></span>
        </div>
      </section>

      <div class="list-heading">
        <h3>Tugas</h3>
        <span>{{ pendingCount }} tersisa</span>
      </div>

      <ul v-if="tasks.length" class="task-list">
        <li v-for="task in tasks" :key="task.id" class="task-row" :class="{ 'is-completed': task.is_done }">
          <input
            class="task-checkbox"
            type="checkbox"
            :checked="task.is_done"
            :aria-label="`${task.is_done ? 'Tandai belum selesai' : 'Tandai selesai'}: ${task.title}`"
            @change="toggle(task)"
          />

          <div class="task-copy">
            <template v-if="editingId === task.id">
              <input
                v-model="editedTitle"
                class="edit-input"
                aria-label="Judul tugas"
                @keyup.enter="saveEdit(task)"
                @keyup.esc="cancelEdit"
              />
              <p v-if="editError" class="edit-error">{{ editError }}</p>
            </template>
            <span v-else class="task-title">{{ task.title }}</span>
          </div>

          <div class="task-actions">
            <template v-if="editingId === task.id">
              <button class="action-button" type="button" :disabled="savingEdit" @click="saveEdit(task)">
                {{ savingEdit ? 'Menyimpan...' : 'Simpan' }}
              </button>
              <button class="action-button quiet-button" type="button" :disabled="savingEdit" @click="cancelEdit">
                Batal
              </button>
            </template>
            <template v-else>
              <button class="action-button quiet-button" type="button" @click="startEdit(task)">Edit</button>
              <button class="action-button delete-button" type="button" @click="remove(task)">Hapus</button>
            </template>
          </div>
        </li>
      </ul>
      <div v-else class="empty-state">
        <p>Belum ada tugas untuk hari ini.</p>
        <span>Tambahkan tugas di atas untuk mulai menyusun rencana.</span>
      </div>
    </section>
  </div>
</template>

<style scoped>
.task-page {
  --page-bg: #18181a;
  --surface: #1f2126;
  --surface-raised: #24262c;
  --line: #34363d;
  --line-soft: #2c2e34;
  --text: #f4f4f5;
  --muted: #a1a1aa;
  --quiet: #777b85;
  --focus: #d4d4d8;
  color: var(--text);
}

.page-header,
.day-heading,
.panel-heading,
.progress-heading,
.list-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-header {
  margin-bottom: 34px;
}

.eyebrow {
  margin: 0 0 7px;
  color: var(--quiet);
  font-size: 10px;
  font-weight: 650;
  letter-spacing: 1.35px;
}

.page-header h1 {
  margin: 0;
  color: var(--text);
  font-size: 29px;
  font-weight: 630;
  letter-spacing: -0.9px;
  line-height: 1.2;
}

.logout-button,
.action-button,
.add-day-form button {
  border: 1px solid var(--line);
  border-radius: 7px;
  background: transparent;
  color: #c6c7cc;
  cursor: pointer;
  transition: background-color 140ms ease, border-color 140ms ease, color 140ms ease;
}

.logout-button {
  padding: 8px 12px;
  font-size: 12px;
}

.logout-button:hover,
.quiet-button:hover,
.add-day-form button:hover {
  border-color: #555861;
  background: #292b31;
  color: var(--text);
}

.day-section {
  margin-bottom: 24px;
}

.day-heading {
  margin-bottom: 12px;
}

.day-heading h2,
.panel-heading h2 {
  margin: 0;
  color: var(--text);
  font-size: 15px;
  font-weight: 580;
}

.muted-copy {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 12px;
}

.day-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.day-tabs {
  display: flex;
  flex: 1;
  gap: 7px;
  min-width: 0;
  overflow-x: auto;
  padding: 1px 1px 5px;
  scrollbar-color: #41434a transparent;
  scrollbar-width: thin;
}

.day-tab {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  gap: 3px;
  min-width: 71px;
  padding: 9px 11px;
  border: 1px solid var(--line-soft);
  border-radius: 7px;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  text-align: left;
  transition: background-color 140ms ease, border-color 140ms ease, color 140ms ease;
}

.day-tab:hover {
  border-color: #51535b;
  color: #e4e4e7;
}

.day-tab.is-active {
  border-color: #777982;
  background: #2a2c32;
  color: var(--text);
}

.day-tab span {
  font-size: 10px;
  text-transform: capitalize;
}

.day-tab strong {
  font-size: 12px;
  font-weight: 600;
}

.add-day-form {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 6px;
}

.add-day-form input {
  width: 132px;
  padding: 8px 8px;
  border: 1px solid var(--line);
  border-radius: 7px;
  outline: none;
  background: var(--surface);
  color: #c6c7cc;
  color-scheme: dark;
  font: inherit;
  font-size: 11px;
}

.add-day-form button {
  width: 33px;
  height: 33px;
  font-size: 20px;
  line-height: 1;
}

.task-panel {
  padding: 22px;
  border: 1px solid #303238;
  border-radius: 10px;
  background: var(--surface);
}

.panel-heading {
  margin-bottom: 18px;
}

.panel-heading .eyebrow {
  margin-bottom: 6px;
}

.task-total {
  color: var(--muted);
  font-size: 11px;
}

.task-form {
  display: flex;
  overflow: hidden;
  border: 1px solid #3f3f46;
  border-radius: 7px;
  background: #1b1c20;
  transition: border-color 140ms ease, box-shadow 140ms ease;
}

.task-form:focus-within {
  border-color: #777982;
  box-shadow: 0 0 0 2px rgba(161, 161, 170, 0.1);
}

.task-form input,
.edit-input {
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--text);
  font: inherit;
  font-size: 13px;
}

.task-form input {
  flex: 1;
  padding: 11px 12px;
}

.task-form input::placeholder {
  color: #777b85;
}

.task-form input:disabled {
  cursor: not-allowed;
}

.add-button {
  flex: 0 0 auto;
  margin: 4px;
  padding: 0 12px;
  border: 0;
  border-radius: 5px;
  background: #e4e4e7;
  color: #202124;
  cursor: pointer;
  font: inherit;
  font-size: 11px;
  font-weight: 650;
  transition: background-color 140ms ease;
}

.add-button:hover:not(:disabled) {
  background: #fff;
}

.add-button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.progress-section {
  padding: 18px 0 19px;
  border-bottom: 1px solid var(--line-soft);
}

.progress-heading {
  margin-bottom: 9px;
  color: var(--muted);
  font-size: 11px;
}

.progress-heading strong {
  margin-left: 8px;
  color: #d4d4d8;
  font-weight: 550;
}

.progress-track {
  height: 3px;
  overflow: hidden;
  border-radius: 4px;
  background: #3a3c42;
}

.progress-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #c4c5ca;
  transition: width 180ms ease;
}

.list-heading {
  margin: 17px 0 4px;
}

.list-heading h3 {
  margin: 0;
  color: #e4e4e7;
  font-size: 12px;
  font-weight: 600;
}

.list-heading span {
  color: var(--quiet);
  font-size: 11px;
}

.task-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 53px;
  border-bottom: 1px solid var(--line-soft);
}

.task-row:last-child {
  border-bottom: 0;
}

.task-checkbox {
  flex: 0 0 auto;
  width: 15px;
  height: 15px;
  margin: 0;
  accent-color: #d4d4d8;
  cursor: pointer;
}

.task-copy {
  flex: 1;
  min-width: 0;
}

.task-title {
  display: block;
  overflow-wrap: anywhere;
  color: #e4e4e7;
  font-size: 13px;
  line-height: 1.45;
}

.is-completed .task-title {
  color: #737780;
  text-decoration: line-through;
}

.edit-input {
  width: 100%;
  box-sizing: border-box;
  padding: 7px 8px;
  border: 1px solid var(--line);
  border-radius: 5px;
  background: #1a1b1f;
}

.edit-input:focus {
  border-color: #777982;
}

.edit-error {
  margin: 4px 0 0;
  color: #e09a9a;
  font-size: 11px;
}

.task-actions {
  display: flex;
  flex: 0 0 auto;
  gap: 5px;
}

.action-button {
  padding: 5px 8px;
  font-size: 11px;
}

.action-button:not(.quiet-button):not(.delete-button) {
  border-color: #d4d4d8;
  background: #d4d4d8;
  color: #202124;
}

.action-button:not(.quiet-button):not(.delete-button):hover {
  border-color: #fff;
  background: #fff;
}

.delete-button {
  border-color: transparent;
  color: #a1a1aa;
}

.delete-button:hover {
  border-color: #583d40;
  background: #302326;
  color: #e6aaaa;
}

.action-button:disabled {
  cursor: wait;
  opacity: 0.55;
}

.empty-state {
  padding: 23px 0 12px;
  color: var(--muted);
  text-align: center;
}

.empty-state p {
  margin: 0;
  color: #d4d4d8;
  font-size: 13px;
}

.empty-state span {
  display: block;
  margin-top: 6px;
  font-size: 11px;
}

.page-error {
  margin: -10px 0 14px;
  color: #e6aaaa;
  font-size: 12px;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  clip-path: inset(50%);
}

:where(button, input):focus-visible {
  outline: 2px solid #a1a1aa;
  outline-offset: 2px;
}

@media (max-width: 600px) {
  .page-header {
    margin-bottom: 26px;
  }

  .page-header h1 {
    font-size: 26px;
  }

  .day-controls {
    align-items: stretch;
    flex-direction: column;
  }

  .add-day-form {
    align-self: flex-start;
  }

  .add-day-form input {
    width: 145px;
  }

  .task-panel {
    padding: 17px 15px;
  }

  .task-row {
    gap: 9px;
  }

  .task-actions {
    gap: 3px;
  }

  .action-button {
    padding: 5px 6px;
    font-size: 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
