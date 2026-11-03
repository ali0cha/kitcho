# Installer Kitcho sur TrueNAS

Fonctionne avec TrueNAS SCALE (24.10 « Electric Eel » et suivants), qui exécute des applications Docker.
Il n'y a rien à compiler sur le NAS : l'image est construite par GitHub et récupérée depuis `ghcr.io/ali0cha/kitcho`.

## 1. Une seule fois, sur GitHub : publier l'image

1. Poussez le dépôt (ou lancez à la main *Actions → Image Docker → Run workflow*). GitHub construit l'image (amd64 + arm64) et la publie.
2. Ouvrez <https://github.com/ali0cha?tab=packages> → paquet **kitcho** → *Package settings* → *Change visibility* → **Public**.
   Sans cela, TrueNAS ne pourrait pas télécharger l'image (le paquet est privé par défaut, comme le dépôt).
   L'image ne contient aucune donnée personnelle : ni vos recettes, ni vos identifiants.
3. (Recommandé) Publiez une version : `git tag v1.0.0 && git push --tags`. L'image `:1.0.0` est alors disponible.

## 2. Sur TrueNAS : installer

1. *Apps → Discover Apps → Custom App → Install via YAML*.
2. Nom de l'application : `kitcho`.
3. Collez le contenu de [`truenas/compose.yaml`](truenas/compose.yaml) et remplacez **les deux** `CHANGEZ-MOI` par un mot de passe de votre choix (le même aux deux endroits).
   Mettez de préférence `image: ghcr.io/ali0cha/kitcho:1.0.0` plutôt que `:latest`.
4. *Save*. Au premier démarrage, l'application attend la base puis crée ses tables toute seule.
5. Ouvrez `http://IP-DU-NAS:30080`, créez votre compte (le premier foyer est créé avec).

Les données (comptes, recettes, menus) sont dans le volume Docker `kitcho_db` de l'application. Variante : un dossier de dataset (ligne commentée dans le YAML) ; il doit appartenir à l'UID/GID 999, sinon MariaDB ne démarre pas.

## 3. Mettre à jour

Publiez une nouvelle version (`git tag v1.0.1 && git push --tags`), attendez la fin du workflow GitHub, puis dans TrueNAS : *Apps → kitcho → Edit*, changez le numéro de version de l'image, *Save*. Les tables sont mises à jour au redémarrage ; les données sont conservées.

## 4. Reprendre vos recettes d'une autre installation

**Recettes et ingrédients (recommandé)** — depuis la page *Recettes* :

1. Sur l'ancienne installation : *Exporter / importer un fichier* → **Exporter mes recettes**. Un fichier `kitcho-recettes-AAAA-MM-JJ.json` est téléchargé (recettes, catégories, liens, notes, favoris, portions et ingrédients).
2. Sur Kitcho (TrueNAS), connecté à votre compte : même endroit → choisissez le fichier → **Importer**.

L'import ne modifie jamais une recette déjà présente (même nom, accents et majuscules ignorés) ; si elle n'a aucun ingrédient et que le fichier en fournit, ils lui sont ajoutés. On peut donc relancer l'import sans risque. Les comptes, foyers et menus planifiés ne sont pas transférés : créez votre compte sur le NAS et invitez les autres membres avec un lien.

Les deux installations doivent avoir la fonction d'export/import : mettez à jour l'image des deux côtés si besoin.

**Tout reprendre (comptes, foyers, menus…)** — export SQL complet. Attention : il remplace le contenu de la base du NAS. Depuis la machine de l'ancienne installation (dossier du projet) :

```bash
docker compose exec -T db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > kitcho.sql
```

Copiez `kitcho.sql` sur le NAS, puis dans le *Shell* de TrueNAS (le nom du conteneur se trouve avec `sudo docker ps | grep db`) :

```bash
sudo docker exec -i NOM-DU-CONTENEUR-DB sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < kitcho.sql
```

## 5. Sauvegardes

- Instantanés ZFS du pool qui héberge les applications (*Data Protection → Periodic Snapshot Tasks*).
- Ou un export SQL régulier : même commande `mariadb-dump` que ci-dessus, lancée depuis le shell du NAS.

## 6. Accès depuis l'extérieur

Placez Kitcho derrière un reverse proxy HTTPS (Nginx Proxy Manager, Traefik, Caddy…). Il doit transmettre `X-Forwarded-Proto: https` : le cookie de session passe alors en `Secure`. N'ouvrez pas le port 30080 directement sur Internet, et n'exposez jamais le port de la base (il n'est pas publié).

## Dépannage

- **L'application ne démarre pas, la base est « unhealthy »** : mot de passe différent entre `DB_PASS` et `MARIADB_PASSWORD`. S'il a été modifié après le premier démarrage, la base garde l'ancien : supprimez le volume `kitcho_db` (⚠ efface les données) ou changez le mot de passe côté MariaDB.
- **Le téléchargement de l'image échoue (« denied »)** : le paquet GitHub est encore privé, voir l'étape 1.2.
- **Port déjà utilisé** : changez `30080` (les ports d'applications TrueNAS sont généralement ≥ 9000).
