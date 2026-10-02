#!/usr/bin/env bash
#
# Crea los symlinks que OpenCode necesita para leer los prompts y agentes de Copilot.
#
# Los ficheros REALES viven solo en .github/ (Copilot los lee de ahí). OpenCode
# espera encontrarlos en .opencode/, así que aquí solo hay enlaces: no hay copia
# ni contenido duplicado. .opencode/ está en .gitignore; ejecuta este script
# después de clonar o cuando añadas un prompt/agente nuevo.
#
# Es idempotente: se puede ejecutar tantas veces como haga falta.

set -euo pipefail

root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

AGENTS_SRC=".github/agents"
PROMPTS_SRC=".github/prompts"
AGENTS_DEST=".opencode/agents"
COMMANDS_DEST=".opencode/commands"

if [[ ! -d "$AGENTS_SRC" || ! -d "$PROMPTS_SRC" ]]; then
    printf 'Error: ejecuta este script desde la raíz del repositorio.\n' >&2
    exit 1
fi

# git config core.symlinks: en Windows sin soporte de symlinks, git los
# materializa como ficheros de texto con la ruta dentro. Un symlink "de verdad"
# apunta a un fichero; un fichero de texto apunta a una ruta inexistente.
symlinks_supported() {
    probe_dir="$(mktemp -d)"
    if ln -s /dev/null "$probe_dir/probe" 2>/dev/null && [[ -L "$probe_dir/probe" ]]; then
        rm -rf "$probe_dir"
        return 0
    fi
    rm -rf "$probe_dir"
    return 1
}

# Crea un enlace y avisa si algo ya estaba ahí con otro contenido.
link() {
    local dest="$1" target="$2" label="$3"

    if [[ -L "$dest" && "$(readlink "$dest")" == "$target" ]]; then
        printf '  ok    %s\n' "$label"
        return 0
    fi

    if [[ -e "$dest" || -L "$dest" ]]; then
        printf '  rm    %s (reemplazando)\n' "$label"
        rm -rf "$dest"
    fi

    if ! ln -s "$target" "$dest"; then
        printf '  ERROR %s: no se pudo crear el symlink\n' "$label" >&2
        return 1
    fi

    printf '  +     %s -> %s\n' "$label" "$target"
}

if ! symlinks_supported; then
    printf 'Error: este sistema no soporta symlinks.\n' >&2
    printf '       Activa git core.symlinks, o configura WSL/devcontainers.\n' >&2
    exit 1
fi

mkdir -p "$AGENTS_DEST" "$COMMANDS_DEST"

printf 'Agentes (.github/agents -> .opencode/agents)\n'
for src in "$AGENTS_SRC"/*.agent.md; do
    [[ -e "$src" ]] || continue
    id="$(basename "$src" .agent.md)"
    link "$AGENTS_DEST/$id.md" "../../$AGENTS_SRC/$id.agent.md" "$id"
done

printf 'Prompts (.github/prompts -> .opencode/commands)\n'
for src in "$PROMPTS_SRC"/*.prompt.md; do
    [[ -e "$src" ]] || continue
    id="$(basename "$src" .prompt.md)"
    link "$COMMANDS_DEST/$id.md" "../../$PROMPTS_SRC/$id.prompt.md" "$id"
done

# Elimina enlaces colgantes: el prompt o agente de origen se borró o renombró.
while IFS= read -r -d '' link_path; do
    if [[ ! -e "$link_path" ]]; then
        printf '  stale %s (origen borrado; lo elimino)\n' "${link_path#"$root"/}"
        rm -f "$link_path"
    fi
done < <(find "$AGENTS_DEST" "$COMMANDS_DEST" -type l -print0)

printf '\nListo. Edita siempre en .github/, nunca en .opencode/.\n'
printf 'Tras editar un prompt o agente, OpenCode no recarga a través del symlink: usa `opencode service restart`.\n'