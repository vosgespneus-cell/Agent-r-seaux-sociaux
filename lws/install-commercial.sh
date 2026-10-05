#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
base=/var/www/vosgespneus.com/home
bundle=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
runtime=/usr/base/opt/php8.3
export PHP_INI_SCAN_DIR="$runtime/etc/conf.d"
php_cli=("$runtime/bin/php" -c "$runtime/etc/php.ini" -d "extension_dir=$runtime/lib/php/extensions/no-debug-non-zts-20230831")
[[ -d "$base" && -f "$base/vp_run.php" ]] || { echo 'Installation LWS existante introuvable'; exit 1; }
[[ -x "${php_cli[0]}" && -f "$bundle/vp_commercial.php" ]] || { echo 'Moteur PHP ou programme manquant'; exit 1; }
target="$base/vp_commercial.php"
stage=$(mktemp "$base/vp_commercial.stage.XXXXXX")
backup="$base/vp_commercial.backup.$(date +%Y%m%dT%H%M%S).$$"
had_previous=0
installed=0
cleanup() { rm -f -- "$stage"; }
rollback() {
    if [[ "$installed" == 1 ]]; then
        if [[ "$had_previous" == 1 ]]; then cp -p -- "$backup" "$target"; else mv -- "$target" "$backup.failed"; fi
    fi
    echo 'Verification echouee : programme precedent preserve, aucune tache cron modifiee' >&2
}
trap cleanup EXIT
trap rollback ERR
cp -- "$bundle/vp_commercial.php" "$stage"
chmod 600 "$stage"
"${php_cli[@]}" -l "$stage"
"${php_cli[@]}" "$stage" --self-test
if [[ -f "$target" ]]; then cp -p -- "$target" "$backup"; had_previous=1; fi
mv -- "$stage" "$target"
installed=1
"${php_cli[@]}" "$target" --force
printf '%s\n' 'MODULE COMMERCIAL INSTALLE ET CONTROLE EN LIGNE' 'Mode : audit et brouillons, publication non connectee' "Rapport prive : $base/vp_commercial/rapport.json" 'La planification cron reste a activer apres verification'
