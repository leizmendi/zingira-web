# Camping Zingira - Web con Astro

Sitio web del Camping Zingira (Aia-Orio, Gipuzkoa) construido con Astro + Tailwind CSS.

## Requisitos

- Node.js >= 18
- npm

## Instalación

```bash
npm install
```

## Desarrollo

```bash
npm run dev
```

## Build

```bash
npm run build
npm run preview
```

## Imágenes necesarias

Coloca las siguientes imágenes en `public/images/`:

| Archivo | Descripción | Fuente original |
|---------|-------------|-----------------|
| `logo-white.png` | Logo blanco para header/footer | `wp-content/uploads/2017/06/logotipo-zingira-kanpina-blanco.png` |
| `logo.jpg` | Logo principal | `wp-content/uploads/2020/07/admin-ajax.php_-2.jpg` |
| `vista-aerea.jpg` | Vista aérea del camping (hero) | `wp-content/uploads/2020/07/vista-de-pajaro-1.jpg` |
| `bungalow.jpg` | Foto bungalow | `wp-content/uploads/2020/07/bungalow-zingira-camping-orio-aia.jpg` |
| `parcelas.jpg` | Foto parcelas tiendas | `wp-content/uploads/2020/07/parcelas-zingira-camping-orio-aia.jpg` |
| `caravanas.jpg` | Foto parcelas caravanas | `wp-content/uploads/2021/04/20210403_07540118514_1.jpg` |
| `restaurante.jpg` | Foto restaurante | `wp-content/uploads/2020/07/camping-zingira-restaurante_1-2.jpg` |

## Estructura del proyecto

```
src/
├── components/         # Componentes reutilizables
│   ├── Header.astro
│   ├── Footer.astro
│   ├── Hero.astro
│   ├── About.astro
│   ├── Accommodation.astro
│   ├── ReviewsAndOffseason.astro
│   └── Contact.astro
├── i18n/
│   └── utils.ts        # Sistema de traducciones (ES, EU, EN, FR)
├── layouts/
│   └── Layout.astro    # Layout principal
└── pages/
    ├── index.astro                     # / (español - default)
    ├── tarifas-y-reservas.astro        # /tarifas-y-reservas
    ├── restaurante.astro               # /restaurante
    ├── eu/                             # Euskera
    ├── en/                             # English
    └── fr/                             # Français
```

## Pendiente

- [ ] Descargar y colocar las imágenes originales de WordPress
- [ ] Integrar motor de reservas de bungalows (widget externo)
- [ ] Configurar envío del formulario de contacto
- [ ] Añadir Google reCAPTCHA al formulario
