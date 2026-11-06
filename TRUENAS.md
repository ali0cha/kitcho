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

## 3. Mettre à jour (et rester à la version du dépôt)

Chaque `git push` sur `main` déclenche le workflow « Image Docker », qui publie l'image `latest` portant le numéro du commit. Le YAML fourni contient `pull_policy: always` : **à chaque *Apps → kitcho → Edit → Save*, TrueNAS télécharge la dernière image** puis recrée le conteneur si elle a changé (la base n'est pas touchée, les tables sont mises à jour au démarrage).

Marche à suivre après un `git push` :

1. Attendez que le workflow soit vert (onglet *Actions* du dépôt, quelques minutes).
2. TrueNAS → *Apps → kitcho → Edit* → *Save*.
3. Vérifiez que tout est aligné, depuis une copie du dépôt :

```bash
bin/check_deploy.sh                # ou : bin/check_deploy.sh https://autre-adresse
```

Le script compare quatre versions (dépôt local, GitHub, image publiée, application en ligne) et dit précisément quoi faire en cas d'écart. La version déployée est aussi visible dans l'appli : *Profil → À propos*, ou `https://kitcho.aliocha.xyz/version`.

Si vous préférez des mises à jour contrôlées, épinglez une version (`image: ghcr.io/ali0cha/kitcho:1.0.1` après `git tag v1.0.1 && git push --tags`), retirez `pull_policy` et changez le numéro pour mettre à jour. Sans le suivi de version (anciennes images), `/version` n'existe pas : le script le signale.

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

## 6. Publier sur Internet en HTTPS (ex. https://kitcho.aliocha.xyz)

Le HTTPS est **indispensable** pour que Kitcho s'installe comme application (PWA) sur un téléphone : sans lui, pas d'icône plein écran ni de mode hors connexion. Il faut un reverse proxy devant l'application (Nginx Proxy Manager, Traefik, Caddy…). Exemple avec **Nginx Proxy Manager** :

1. **DNS** : chez votre registrar, un enregistrement `A` (et `AAAA` si IPv6) `kitcho.aliocha.xyz` → l'adresse IP publique de votre box.
2. **Box / routeur** : redirigez les ports 80 et 443 vers la machine qui héberge Nginx Proxy Manager.
3. **Nginx Proxy Manager** → *Hosts → Proxy Hosts → Add Proxy Host* :
   - *Domain Names* : `kitcho.aliocha.xyz`
   - *Scheme* : `http` · *Forward Hostname / IP* : l'adresse du NAS · *Forward Port* : `30080`
   - cochez *Block Common Exploits*
   - onglet *SSL* : *Request a new SSL Certificate* (Let's Encrypt), cochez *Force SSL* et *HTTP/2 Support* (et *HSTS* si vous êtes sûr de garder le HTTPS)
4. Ouvrez https://kitcho.aliocha.xyz, créez votre compte.

Le proxy doit transmettre l'en-tête `X-Forwarded-Proto: https` (Nginx Proxy Manager le fait tout seul) : Kitcho passe alors le cookie de session en `Secure`, génère des liens d'invitation en `https://` et envoie l'en-tête HSTS.

**Une fois en ligne :**
- Dans le YAML TrueNAS, mettez `ALLOW_REGISTRATION: "false"` dès que vos comptes sont créés : n'importe qui pourrait sinon s'inscrire. Les nouvelles personnes entrent alors uniquement avec un lien d'invitation.
- N'ouvrez jamais directement le port 30080 sur Internet, et n'exposez jamais le port de la base (il n'est pas publié).

## 6 bis. Installer l'application sur un téléphone

Ouvrez https://kitcho.aliocha.xyz dans le navigateur, connectez-vous, puis :

- **Android (Chrome)** : menu ⋮ → *Installer l'application* (ou le bouton dans *Profil → Application*). Kitcho s'ouvre en plein écran, avec son icône.
- **iPhone / iPad (Safari)** : bouton Partager → *Sur l'écran d'accueil*.
- **Ordinateur (Chrome, Edge)** : icône d'installation dans la barre d'adresse.

L'application garde en mémoire les dernières pages consultées (liste de courses, semaine…) : elles restent lisibles sans réseau, et les coches faites hors connexion sont envoyées au retour de la connexion. Les pages gardées sont effacées à la déconnexion. En plein écran, il n'y a pas de bouton « retour » du navigateur : l'application affiche ses propres liens de retour.

## Ajouter les étapes à vos recettes existantes

Si vos recettes ont un lien (site de cuisine) mais pas d'étapes, une seule commande les complète depuis le *Shell* TrueNAS. Commencez par une simulation, qui n'écrit rien :

```bash
sudo docker exec -it NOM-DU-CONTENEUR-APP php bin/enrich_recipes.php          # liste les foyers et leur numéro
sudo docker exec -it NOM-DU-CONTENEUR-APP php bin/enrich_recipes.php 1 --dry-run
sudo docker exec -it NOM-DU-CONTENEUR-APP php bin/enrich_recipes.php 1
```

Les recettes qui ont déjà des étapes ne sont pas touchées, ce qui existe n'est jamais écrasé, et la commande peut être relancée sans risque. Le NAS doit pouvoir joindre Internet.

## Mot de passe oublié

Il n'y a pas d'envoi d'e-mail : on réinitialise le mot de passe depuis le *Shell* de TrueNAS. Trouvez d'abord le nom du conteneur de l'application (`sudo docker ps | grep kitcho`, celui qui expose le port 30080), puis :

```bash
sudo docker exec -it NOM-DU-CONTENEUR-APP php bin/reset_password.php votre@email
```

Le script demande le nouveau mot de passe (saisie masquée) ; sans e-mail, il liste les comptes. Il lève aussi un éventuel blocage après trop d'essais ratés.

Si votre image est antérieure à ce script, utilisez cette variante (le mot de passe n'apparaît pas dans l'historique du shell) :

```bash
read -rsp "Nouveau mot de passe (8 caractères min.) : " NEWPW; echo
sudo docker exec -e NEWPW="$NEWPW" -e EMAIL=votre@email NOM-DU-CONTENEUR-APP php -r '$p=new PDO("mysql:host=".getenv("DB_HOST").";dbname=".getenv("DB_NAME"),getenv("DB_USER"),getenv("DB_PASS")); $s=$p->prepare("UPDATE users SET password_hash=? WHERE email=?"); $s->execute([password_hash(getenv("NEWPW"),PASSWORD_DEFAULT),strtolower(getenv("EMAIL"))]); $p->prepare("DELETE FROM login_attempts WHERE email=?")->execute([strtolower(getenv("EMAIL"))]); echo $s->rowCount()," compte(s) modifié(s)\n";'
unset NEWPW
```

## Dépannage

- **L'application ne démarre pas, la base est « unhealthy »** : mot de passe différent entre `DB_PASS` et `MARIADB_PASSWORD`. S'il a été modifié après le premier démarrage, la base garde l'ancien : supprimez le volume `kitcho_db` (⚠ efface les données) ou changez le mot de passe côté MariaDB.
- **Le téléchargement de l'image échoue (« denied »)** : le paquet GitHub est encore privé, voir l'étape 1.2.
- **Port déjà utilisé** : changez `30080` (les ports d'applications TrueNAS sont généralement ≥ 9000).
