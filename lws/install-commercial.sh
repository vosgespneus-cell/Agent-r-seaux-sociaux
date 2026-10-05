#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
base=/var/www/vosgespneus.com/home
script_path=${BASH_SOURCE[0]}
bundle_dir=.
[[ "$script_path" != */* ]] || bundle_dir=${script_path%/*}
bundle=$(cd -- "$bundle_dir" && pwd)
runtime=/usr/base/opt/php8.3
export PHP_INI_SCAN_DIR="$runtime/etc/conf.d"
php_cli=("$runtime/bin/php" -c "$runtime/etc/php.ini" -d "extension_dir=$runtime/lib/php/extensions/no-debug-non-zts-20230831")
[[ -d "$base" && -f "$base/vp_run.php" ]] || { echo 'Installation LWS existante introuvable'; exit 1; }
[[ -x "${php_cli[0]}" && -f "$bundle/vp_commercial.php" ]] || { echo 'Moteur PHP ou programme manquant'; exit 1; }
[[ -f "$bundle/vp_stock.php" && -f "$base/vp_commercial/shopify_credentials.json" ]] || { echo 'Lecteur stock ou identifiants Shopify prives manquants'; exit 1; }
target="$base/vp_commercial.php"
stock_target="$base/vp_stock.php"
stock_stage="$base/vp_stock.stage.$RANDOM.php"
stock_backup="$base/vp_stock.backup.$RANDOM.php"
stock_installed=0
stock_previous=0
stage="$base/vp_commercial.stage.$.$RANDOM"
(set -o noclobber; : > "$stage")
backup="$base/vp_commercial.backup.$.$RANDOM"
had_previous=0
installed=0
cleanup() { rm -f -- "$stage" "$stock_stage"; }
rollback() {
    if [[ "$stock_installed" == 1 ]]; then
        if [[ "$stock_previous" == 1 ]]; then cp -p -- "$stock_backup" "$stock_target"; else mv -- "$stock_target" "$stock_backup.failed"; fi
    fi
    if [[ "$installed" == 1 ]]; then
        if [[ "$had_previous" == 1 ]]; then cp -p -- "$backup" "$target"; else mv -- "$target" "$backup.failed"; fi
    fi
    echo 'Verification echouee : programme precedent preserve, aucune tache cron modifiee' >&2
}
trap cleanup EXIT
trap rollback ERR
(set -o noclobber; : > "$stock_stage")
cp -- "$bundle/vp_stock.php" "$stock_stage"
chmod 600 "$stock_stage"
"${php_cli[@]}" -l "$stock_stage"
"${php_cli[@]}" "$stock_stage" --self-test
if [[ -f "$stock_target" ]]; then cp -p -- "$stock_target" "$stock_backup"; stock_previous=1; fi
mv -- "$stock_stage" "$stock_target"
stock_installed=1
cp -- "$bundle/vp_commercial.php" "$stage"
chmod 600 "$stage"
"${php_cli[@]}" -l "$stage"
"${php_cli[@]}" "$stage" --self-test
if [[ -f "$target" ]]; then cp -p -- "$target" "$backup"; had_previous=1; fi
mv -- "$stage" "$target"
installed=1
"${php_cli[@]}" "$target" --force
printf '%s\n' 'MODULE COMMERCIAL INSTALLE ET CONTROLE EN LIGNE' 'Mode : audit et brouillons, publication non connectee' "Rapport prive : $base/vp_commercial/rapport.json" 'La planification cron reste a activer apres verification'
