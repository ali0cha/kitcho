#!/usr/bin/env bash
# Vérifie que le dépôt, l'image publiée et l'application en ligne (NAS) sont à la même version.
#   bin/check_deploy.sh [url-de-l-appli]     défaut : https://kitcho.aliocha.xyz
# À lancer depuis une copie du dépôt (git, curl et python3 suffisent).
set -u
URL="${1:-https://kitcho.aliocha.xyz}"
IMAGE_REPO="${IMAGE_REPO:-ali0cha/kitcho}"
short() { printf '%s' "${1:0:7}"; }
ok=0

LOCAL=$(git rev-parse HEAD 2>/dev/null) || { echo "À lancer depuis le dépôt git."; exit 2; }
REMOTE=$(git ls-remote origin refs/heads/main 2>/dev/null | cut -c1-40)

IMAGE=$(python3 - "$IMAGE_REPO" <<'PY'
import json, subprocess, sys
repo = sys.argv[1]
def curl(args):
    return subprocess.run(["curl", "-sL", "-m", "20"] + args, capture_output=True, text=True).stdout
try:
    tok = json.loads(curl([f"https://ghcr.io/token?service=ghcr.io&scope=repository:{repo}:pull"]))["token"]
    h = ["-H", "Authorization: Bearer " + tok]
    acc = "application/vnd.oci.image.index.v1+json,application/vnd.docker.distribution.manifest.list.v2+json,application/vnd.oci.image.manifest.v1+json,application/vnd.docker.distribution.manifest.v2+json"
    m = json.loads(curl(h + ["-H", "Accept: " + acc, f"https://ghcr.io/v2/{repo}/manifests/latest"]))
    if "manifests" in m:
        d = [x for x in m["manifests"] if x.get("platform", {}).get("architecture") == "amd64"][0]["digest"]
        m = json.loads(curl(h + ["-H", "Accept: application/vnd.oci.image.manifest.v1+json,application/vnd.docker.distribution.manifest.v2+json", f"https://ghcr.io/v2/{repo}/manifests/{d}"]))
    cfg = json.loads(curl(h + [f"https://ghcr.io/v2/{repo}/blobs/" + m["config"]["digest"]]))
    print(cfg.get("config", {}).get("Labels", {}).get("org.opencontainers.image.revision", ""))
except Exception:
    print("")
PY
)
LIVE=$(curl -fsS -m 20 "$URL/version" 2>/dev/null | python3 -c 'import sys,json; print(json.load(sys.stdin).get("sha") or "")' 2>/dev/null)

row() { printf '%s%*s %s\n' "$1" $((26 - ${#1})) '' "$2"; }   # ${#1} compte les caractères (accents)
echo "Version attendue : $(short "$LOCAL") (dernier commit de ce dossier)"
echo
row "Dépôt local"            "$(short "$LOCAL")"
row "GitHub (main)"          "$([ -n "$REMOTE" ] && short "$REMOTE" || echo 'injoignable')"
row "Image ghcr.io (latest)" "$([ -n "$IMAGE" ] && short "$IMAGE" || echo 'inconnue')"
row "Application en ligne"   "$([ -n "$LIVE" ] && short "$LIVE" || echo 'inconnue (version antérieure au suivi, ou site injoignable)')"
echo

[ -n "$REMOTE" ] && [ "$REMOTE" != "$LOCAL" ] && { echo "✗ Votre dossier local n'est pas le même que GitHub : git push (ou git pull)."; ok=1; }
if [ -z "$IMAGE" ]; then echo "? Image publiée illisible : vérifiez que le paquet est public et que le workflow « Image Docker » est passé."; ok=1
elif [ "$IMAGE" != "$LOCAL" ] && [ "$IMAGE" != "$REMOTE" ]; then echo "✗ L'image publiée ($(short "$IMAGE")) est en retard sur GitHub : attendez la fin du workflow « Image Docker » (onglet Actions)."; ok=1; fi
if [ -z "$LIVE" ]; then echo "✗ Le NAS fait tourner une version antérieure au suivi de version : mettez-le à jour (voir TRUENAS.md, « Mise à jour »)."; ok=1
elif [ "$LIVE" != "${REMOTE:-$LOCAL}" ]; then echo "✗ Le NAS est sur $(short "$LIVE"), GitHub est sur $(short "${REMOTE:-$LOCAL}") : TrueNAS → Apps → kitcho → Edit → Save (télécharge la dernière image)."; ok=1; fi
[ $ok -eq 0 ] && echo "✓ Dépôt, image et NAS sont à la même version ($(short "$LOCAL"))."
exit $ok
