# Tokens de diseño

Propuesta inicial. El color de marca está pendiente de confirmar: parte del negro/gris sobrio actual y añade un único acento azul.
Cambia los valores aquí y en `resources/css/app.css`; no añadas colores sueltos en las vistas.

## Color (`@theme`)

```css
@theme {
    /* Marca: un único acento */
    --color-brand-50: #eef3fd;
    --color-brand-100: #dbe6fb;
    --color-brand-600: #2b59c3;
    --color-brand-700: #2347a0;

    /* Semánticos */
    --color-success-600: #15803d;
    --color-warning-600: #b45309;
    --color-danger-600: #b91c1c;
}
```

- Neutros: usa la escala `zinc-*` que ya emplea Flux. Texto principal `zinc-900`, secundario `zinc-600`, bordes `zinc-200`, lienzo `zinc-50`.
- El rojo (`danger`) solo para acciones destructivas y errores.
- Acento de Flux: el starter kit define `--color-accent*` en `resources/css/app.css`. Apúntalo a `brand-600` en vez de repetir clases (verifica el nombre exacto en el fichero).
- Modo oscuro: superficies en 2–3 niveles de gris (`zinc-950`, `zinc-900`, `zinc-800`), no una simple inversión. Comprueba contraste AA.

## Tipografía

- Familia: Instrument Sans (ya cargada con Bunny). No añadir otra.
- Escala: 12 / 14 / 16 / 20 / 24 px. Título de página 24 px (peso 600), título de sección 16–20 px (600), etiquetas y cabeceras de tabla 12–13 px (500), cuerpo y tablas 14 px.
- Líneas de texto de menos de 80 caracteres. Sentence case en todo; sin mayúsculas en etiquetas.

## Espaciado, radios y sombras

- Escala de 4 px. Separación entre secciones 24–32 px, dentro de tarjetas 16–20 px.
- Radios por jerarquía, no uno solo: controles `rounded-md`, tarjetas y tablas `rounded-lg`, drawer y modal `rounded-xl`.
- Sombras: casi ninguna. Borde `zinc-200` en tarjetas; sombra suave solo en drawer, modal y menús.

## Tamaños de control

| Elemento | Escritorio | Táctil |
|---|---|---|
| Botón / input | `h-9` (36 px) | `h-11` (44 px) |
| Botón de icono | 32 px | 44 px |
| Fila de tabla | 44–48 px | tarjeta |

## Patrones de referencia

- **Cabecera de página:** `flex items-start justify-between gap-4` con H1 + descripción a la izquierda y botón primario a la derecha.
- **Estado vacío:** icono sencillo, frase de una línea, botón de crear.
- **Badge de estado:** texto + color tenue (`bg-*-50 text-*-700`), nunca solo color.
- **Foco:** `focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2`.