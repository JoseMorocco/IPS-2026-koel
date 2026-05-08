<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 900px; margin: auto; padding: 20px; background-color: #f4f4f9; }
  .header { background: #800000; color: white; padding: 2rem; text-align: center; border-radius: 10px; margin-bottom: 20px; }
  .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .btn { display: inline-block; background: #800000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
  .status { background: #e7f3ff; color: #004085; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; }
  th { background-color: #f8f9fa; }
</style>
</head>
<body>

<div class="header">
  <h1>Proyecto Ingeniería y Procesos de Software</h1>
  <p>UNSA - Facultad de Ingeniería de Producción y Servicios</p>
  <strong>Sprint 0: Configuración y Plan de Proyecto</strong>
</div>

<div class="grid">
  <div class="card">
    <h3>Equipo de Trabajo</h3>
    <ul>
      <li><strong>Líder S0:</strong> Jose Manuel Morocco Saico</li>
      <li>Camargo Hilachoque, Romina</li>
      <li>Hatches Curo, José León</li>
      <li>Mayta Quispe, Paola Adamari</li>
      <li>Cutimbo Quispe, Keneth Sebastian</li>
    </ul>
  </div>
  
  <div class="card">
    <h3>Producto Seleccionado</h3>
    <p><strong>Software:</strong> Koel (Streaming OSS)</p>
    <p><strong>Stack:</strong> Laravel + Vue.js + Docker</p>
    <span class="status">Licencia MIT Verificada</span>
  </div>
</div>

<div class="card">
  <h3>Gestión del Proyecto</h3>
  <p>Estamos utilizando el marco de trabajo <strong>Scrum</strong> para la ejecución de los sprints y <strong>GitHub Actions</strong> para el pipeline DevOps.</p>
  <a href="https://github.com/users/JoseMorocco/projects/1" class="btn">Ir al Tablero Kanban</a>
</div>

<div class="card">
  <h3>Cronograma de Sprints</h3>
  <table>
    <tr><th>Hito</th><th>Fecha</th><th>Entregable</th></tr>
    <tr><td>Hito 1</td><td>13/05/2026</td><td>Sprint 0: Plan de Proyecto</td></tr>
    <tr><td>Hito 2</td><td>10/06/2026</td><td>Sprint 1-2: CI/CD & Docker</td></tr>
    <tr><td>Hito 3</td><td>13/07/2026</td><td>Sprint 3-4: Mejora & IEEE</td></tr>
  </table>
</div>

</body>
</html>
