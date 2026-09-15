# EJEMPLOS DE USO - API REST CATEGORÍAS FINANCIERAS

## 🧪 Ejemplos con cURL

### 1. Autenticación

Para usar la API REST, necesitas autenticarte. WordPress soporta varios métodos:

#### Método 1: Application Passwords (Recomendado)
```bash
# Configurar Application Password en WP Admin:
# Perfil de Usuario → Application Passwords → Crear nueva
# Usar formato: usuario:xxxx xxxx xxxx xxxx

export WP_USER="admin"
export WP_PASS="xxxx xxxx xxxx xxxx"
export API_URL="http://localhost/wp-json/aura/v1/finance/categories"
```

#### Método 2: Cookie Authentication (Solo desde admin)
Si ejecutas desde la consola del navegador mientras estás logueado en admin, las cookies se envían automáticamente.

---

### 2. GET - Listar todas las categorías

```bash
curl -X GET "${API_URL}" \
  -u "${WP_USER}:${WP_PASS}" \
  -H "Content-Type: application/json"
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "Salarios",
      "slug": "salarios",
      "type": "income",
      "parent_id": 0,
      "color": "#3498db",
      "icon": "dashicons-money-alt",
      "status": "active",
      "transactions": 45
    }
  ],
  "total": 1
}
```

---

### 3. GET - Filtrar categorías

#### Por tipo (income/expense)
```bash
curl -X GET "${API_URL}?type=income" \
  -u "${WP_USER}:${WP_PASS}"
```

#### Por estado
```bash
curl -X GET "${API_URL}?status=active" \
  -u "${WP_USER}:${WP_PASS}"
```

#### Por categoría padre
```bash
curl -X GET "${API_URL}?parent_id=100" \
  -u "${WP_USER}:${WP_PASS}"
```

#### Búsqueda por nombre
```bash
curl -X GET "${API_URL}?search=salario" \
  -u "${WP_USER}:${WP_PASS}"
```

#### Combinación de filtros
```bash
curl -X GET "${API_URL}?type=expense&status=active&search=trans" \
  -u "${WP_USER}:${WP_PASS}"
```

---

### 4. POST - Crear nueva categoría

```bash
curl -X POST "${API_URL}" \
  -u "${WP_USER}:${WP_PASS}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Honorarios Profesionales",
    "type": "income",
    "parent_id": 0,
    "color": "#9b59b6",
    "icon": "dashicons-businessman",
    "description": "Ingresos por servicios profesionales",
    "status": "active",
    "display_order": 1
  }'
```

**Respuesta (201 Created):**
```json
{
  "success": true,
  "message": "Categoría creada exitosamente.",
  "data": {
    "id": 124,
    "name": "Honorarios Profesionales",
    "slug": "honorarios-profesionales",
    "type": "income",
    "parent_id": 0,
    "color": "#9b59b6",
    "icon": "dashicons-businessman",
    "description": "Ingresos por servicios profesionales",
    "status": "active",
    "order": 1,
    "transactions": 0,
    "created_at": "2024-01-20 15:30:00",
    "updated_at": "2024-01-20 15:30:00"
  }
}
```

---

### 5. GET - Obtener categoría específica

```bash
curl -X GET "${API_URL}/124" \
  -u "${WP_USER}:${WP_PASS}"
```

---

### 6. PUT - Actualizar categoría

#### Actualizar todos los campos
```bash
curl -X PUT "${API_URL}/124" \
  -u "${WP_USER}:${WP_PASS}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Honorarios y Consultorías",
    "description": "Ingresos por servicios profesionales y consultorías",
    "color": "#8e44ad",
    "icon": "dashicons-businessman"
  }'
```

#### Actualizar solo el nombre
```bash
curl -X PUT "${API_URL}/124" \
  -u "${WP_USER}:${WP_PASS}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Honorarios Actualizados"
  }'
```

#### Cambiar padre (mover en jerarquía)
```bash
curl -X PUT "${API_URL}/124" \
  -u "${WP_USER}:${WP_PASS}" \
  -H "Content-Type: application/json" \
  -d '{
    "parent_id": 100
  }'
```

---

### 7. DELETE - Eliminar categoría

#### Soft delete (mover a papelera)
```bash
curl -X DELETE "${API_URL}/124" \
  -u "${WP_USER}:${WP_PASS}"
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Categoría eliminada exitosamente."
}
```

#### Hard delete (eliminar permanentemente)
```bash
curl -X DELETE "${API_URL}/124?force=true" \
  -u "${WP_USER}:${WP_PASS}"
```

#### Error: Categoría con transacciones
```bash
curl -X DELETE "${API_URL}/123" \
  -u "${WP_USER}:${WP_PASS}"
```

**Respuesta (400 Bad Request):**
```json
{
  "code": "category_has_transactions",
  "message": "No se puede eliminar. Esta categoría tiene 45 transacción(es) asociada(s).",
  "data": {
    "status": 400,
    "transaction_count": 45
  }
}
```

---

### 8. GET - Árbol jerárquico completo

```bash
curl -X GET "${API_URL}/tree" \
  -u "${WP_USER}:${WP_PASS}"
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 100,
      "name": "Ingresos",
      "type": "income",
      "color": "#27ae60",
      "icon": "dashicons-plus-alt",
      "children": [
        {
          "id": 101,
          "name": "Salarios",
          "parent_id": 100,
          "children": [
            {
              "id": 102,
              "name": "Nómina Base",
              "parent_id": 101,
              "children": []
            },
            {
              "id": 103,
              "name": "Bonos",
              "parent_id": 101,
              "children": []
            }
          ]
        },
        {
          "id": 104,
          "name": "Ventas",
          "parent_id": 100,
          "children": []
        }
      ]
    }
  ]
}
```

#### Filtrar árbol por tipo
```bash
curl -X GET "${API_URL}/tree?type=expense" \
  -u "${WP_USER}:${WP_PASS}"
```

---

## 🌐 Ejemplos con JavaScript (Fetch API)

### Setup inicial

```javascript
// Configuración base
const API_BASE = 'http://tudominio.com/wp-json/aura/v1/finance/categories';
const AUTH = 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx');

const headers = {
    'Authorization': AUTH,
    'Content-Type': 'application/json'
};
```

### GET - Listar categorías

```javascript
// Listar todas
async function getCategorias() {
    const response = await fetch(API_BASE, {
        method: 'GET',
        headers: headers
    });
    const data = await response.json();
    console.log(data);
    return data.data; // Array de categorías
}

// Con filtros
async function getCategoriasIngresos() {
    const response = await fetch(`${API_BASE}?type=income&status=active`, {
        method: 'GET',
        headers: headers
    });
    const data = await response.json();
    return data.data;
}

// Buscar
async function buscarCategoria(termino) {
    const response = await fetch(`${API_BASE}?search=${encodeURIComponent(termino)}`, {
        method: 'GET',
        headers: headers
    });
    const data = await response.json();
    return data.data;
}
```

### POST - Crear categoría

```javascript
async function crearCategoria(datosCategoria) {
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                name: datosCategoria.name,
                type: datosCategoria.type, // 'income' o 'expense'
                parent_id: datosCategoria.parent_id || 0,
                color: datosCategoria.color || '#3498db',
                icon: datosCategoria.icon || 'dashicons-category',
                description: datosCategoria.description || '',
                status: 'active'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            console.log('Categoría creada:', data.data);
            return data.data;
        } else {
            console.error('Error:', data.message);
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al crear categoría:', error);
        throw error;
    }
}

// Uso
crearCategoria({
    name: 'Combustible',
    type: 'expense',
    color: '#e74c3c',
    icon: 'dashicons-car'
});
```

### PUT - Actualizar categoría

```javascript
async function actualizarCategoria(id, cambios) {
    const response = await fetch(`${API_BASE}/${id}`, {
        method: 'PUT',
        headers: headers,
        body: JSON.stringify(cambios)
    });
    
    const data = await response.json();
    
    if (response.ok) {
        console.log('Categoría actualizada:', data.data);
        return data.data;
    } else {
        throw new Error(data.message);
    }
}

// Uso
actualizarCategoria(124, {
    name: 'Nuevo nombre',
    color: '#2ecc71'
});
```

### DELETE - Eliminar categoría

```javascript
async function eliminarCategoria(id, permanente = false) {
    const url = permanente ? `${API_BASE}/${id}?force=true` : `${API_BASE}/${id}`;
    
    const response = await fetch(url, {
        method: 'DELETE',
        headers: headers
    });
    
    const data = await response.json();
    
    if (response.ok) {
        console.log('Categoría eliminada');
        return true;
    } else {
        console.error('Error:', data.message);
        throw new Error(data.message);
    }
}

// Uso
try {
    await eliminarCategoria(124);
} catch (error) {
    if (error.message.includes('transacciones')) {
        console.log('No se puede eliminar: tiene transacciones asociadas');
    }
}
```

### GET - Árbol jerárquico

```javascript
async function getArbolCategorias(tipo = null) {
    const url = tipo ? `${API_BASE}/tree?type=${tipo}` : `${API_BASE}/tree`;
    
    const response = await fetch(url, {
        method: 'GET',
        headers: headers
    });
    
    const data = await response.json();
    return data.data;
}

// Renderizar árbol en select
async function renderCategorySelect(selectElement, tipo = null) {
    const arbol = await getArbolCategorias(tipo);
    
    function renderOptions(categorias, nivel = 0) {
        categorias.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.text = '—'.repeat(nivel) + ' ' + cat.name;
            selectElement.appendChild(option);
            
            if (cat.children && cat.children.length > 0) {
                renderOptions(cat.children, nivel + 1);
            }
        });
    }
    
    renderOptions(arbol);
}
```

---

## ⚛️ Ejemplo completo con VueJS 3

```vue
<template>
  <div class="categorias-manager">
    <h2>Gestión de Categorías Financieras</h2>
    
    <!-- Filtros -->
    <div class="filters">
      <select v-model="filters.type" @change="loadCategories">
        <option value="">Todos los tipos</option>
        <option value="income">Ingresos</option>
        <option value="expense">Gastos</option>
      </select>
      
      <input 
        v-model="filters.search" 
        @input="debounceSearch"
        placeholder="Buscar categoría..."
      >
    </div>
    
    <!-- Lista de categorías -->
    <table>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Tipo</th>
          <th>Color</th>
          <th>Transacciones</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="cat in categorias" :key="cat.id">
          <td>
            <span :class="`dashicons ${cat.icon}`" :style="{color: cat.color}"></span>
            {{ cat.name }}
          </td>
          <td>{{ cat.type === 'income' ? 'Ingreso' : 'Gasto' }}</td>
          <td>
            <div class="color-box" :style="{backgroundColor: cat.color}"></div>
          </td>
          <td>{{ cat.transactions }}</td>
          <td>
            <button @click="editCategory(cat)">Editar</button>
            <button @click="deleteCategory(cat.id)">Eliminar</button>
          </td>
        </tr>
      </tbody>
    </table>
    
    <!-- Modal de crear/editar -->
    <div v-if="showModal" class="modal">
      <form @submit.prevent="saveCategory">
        <h3>{{ editingId ? 'Editar' : 'Nueva' }} Categoría</h3>
        
        <input v-model="form.name" placeholder="Nombre" required>
        
        <select v-model="form.type" required>
          <option value="income">Ingreso</option>
          <option value="expense">Gasto</option>
        </select>
        
        <input v-model="form.color" type="color">
        
        <input v-model="form.icon" placeholder="dashicons-category">
        
        <textarea v-model="form.description" placeholder="Descripción"></textarea>
        
        <button type="submit">Guardar</button>
        <button type="button" @click="closeModal">Cancelar</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const API_BASE = 'http://tudominio.com/wp-json/aura/v1/finance/categories';
const AUTH = 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx');

const categorias = ref([]);
const showModal = ref(false);
const editingId = ref(null);

const filters = reactive({
  type: '',
  search: ''
});

const form = reactive({
  name: '',
  type: 'income',
  color: '#3498db',
  icon: 'dashicons-category',
  description: ''
});

// Cargar categorías
async function loadCategories() {
  const params = new URLSearchParams();
  if (filters.type) params.append('type', filters.type);
  if (filters.search) params.append('search', filters.search);
  
  const url = params.toString() ? `${API_BASE}?${params}` : API_BASE;
  
  try {
    const response = await fetch(url, {
      headers: {
        'Authorization': AUTH,
        'Content-Type': 'application/json'
      }
    });
    const data = await response.json();
    categorias.value = data.data;
  } catch (error) {
    console.error('Error al cargar categorías:', error);
  }
}

// Guardar categoría (crear o actualizar)
async function saveCategory() {
  const url = editingId.value ? `${API_BASE}/${editingId.value}` : API_BASE;
  const method = editingId.value ? 'PUT' : 'POST';
  
  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        'Authorization': AUTH,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(form)
    });
    
    const data = await response.json();
    
    if (response.ok) {
      alert(data.message);
      closeModal();
      loadCategories();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error al guardar:', error);
    alert('Error al guardar la categoría');
  }
}

// Editar categoría
function editCategory(cat) {
  editingId.value = cat.id;
  form.name = cat.name;
  form.type = cat.type;
  form.color = cat.color;
  form.icon = cat.icon;
  form.description = cat.description;
  showModal.value = true;
}

// Eliminar categoría
async function deleteCategory(id) {
  if (!confirm('¿Estás seguro de eliminar esta categoría?')) return;
  
  try {
    const response = await fetch(`${API_BASE}/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': AUTH
      }
    });
    
    const data = await response.json();
    
    if (response.ok) {
      alert('Categoría eliminada');
      loadCategories();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error al eliminar:', error);
  }
}

function closeModal() {
  showModal.value = false;
  editingId.value = null;
  Object.keys(form).forEach(key => {
    if (key === 'type') form[key] = 'income';
    else if (key === 'color') form[key] = '#3498db';
    else if (key === 'icon') form[key] = 'dashicons-category';
    else form[key] = '';
  });
}

// Debounce para búsqueda
let debounceTimer;
function debounceSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    loadCategories();
  }, 500);
}

onMounted(() => {
  loadCategories();
});
</script>

<style scoped>
.categorias-manager {
  padding: 20px;
}

.filters {
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.color-box {
  width: 30px;
  height: 30px;
  border-radius: 4px;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal form {
  background: white;
  padding: 30px;
  border-radius: 8px;
  min-width: 400px;
}

.modal input,
.modal select,
.modal textarea {
  display: block;
  width: 100%;
  margin-bottom: 15px;
  padding: 8px;
}
</style>
```

---

## 🔐 Configurar Application Passwords

1. Ve a **Perfil de Usuario** en WordPress admin
2. Desplázate hasta **Application Passwords**
3. Ingresa un nombre (ej: "API REST AURA")
4. Click en **Add New Application Password**
5. Copia la contraseña generada (formato: xxxx xxxx xxxx xxxx)
6. Úsala en lugar de tu contraseña normal para la API

**Importante:** Las Application Passwords solo funcionan si WordPress está en HTTPS o localhost.

---

## 🧪 Probar endpoints rápidamente

### Usando Postman

1. Importar colección desde [aquí o crear manualmente]
2. Configurar variables de entorno:
   - `base_url`: http://tudominio.com
   - `username`: admin
   - `app_password`: xxxx xxxx xxxx xxxx
3. Usar Authorization Type: "Basic Auth"

### Desde navegador (con extensión)

Instalar extensión REST Client para Chrome/Firefox y probar endpoints directamente.

---

**¡API lista para usar! 🚀**
