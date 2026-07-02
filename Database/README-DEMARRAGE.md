# VDM Escape Game — Procédure de démarrage

Commandes à exécuter après un redémarrage du PC pour retrouver l'accès à la base MySQL via TablePlus.

## 1. Lancer Docker Desktop (Windows)

Menu Démarrer → **Docker Desktop** → attendre que le statut soit "Engine running" (icône baleine stable).

## 2. Vérifier que le MySQL natif WSL est bien arrêté

Le service a été désactivé (`systemctl disable mysql`), il ne devrait plus démarrer automatiquement. Pour vérifier dans le terminal WSL (Ubuntu) :

```bash
sudo ss -tlnp | grep 3306
```

- **Rien ne s'affiche** → OK, le port est libre.
- **Un `mysqld` apparaît** → l'arrêter :

```bash
sudo service mysql stop
```

## 3. Démarrer le conteneur MySQL

```bash
cd ~/group-1075831-main/rendu-generator
docker compose up -d
```

Vérifier qu'il tourne :

```bash
docker ps
```

Le conteneur `vdm_mysql` doit apparaître avec le statut `Up`.

## 4. Vérifier que la base répond

```bash
docker exec -it vdm_mysql mysql -uroot -p12345678 escape_game -e "SELECT COUNT(*) FROM reservation;"
```

Résultat attendu : `500`.

> Les données sont persistées dans le volume Docker `vdm_data` : elles survivent aux redémarrages. Pas besoin de relancer le générateur.

## 5. Se connecter avec TablePlus (Windows)

| Champ    | Valeur          |
|----------|-----------------|
| Host     | `127.0.0.1`     |
| Port     | `3306`          |
| User     | `root`          |
| Password | `12345678`      |
| Database | `escape_game`   |

## Dépannage rapide

| Problème | Cause probable | Solution |
|----------|----------------|----------|
| `docker: command not found` dans WSL | Docker Desktop pas lancé ou intégration WSL désactivée | Lancer Docker Desktop ; Settings → Resources → WSL Integration → activer Ubuntu |
| `Access denied for user 'root'@'localhost'` dans TablePlus | Le MySQL natif WSL a redémarré et intercepte le port 3306 | `sudo service mysql stop` puis retester |
| `port is already allocated` au `docker compose up` | Un autre process occupe le 3306 | `sudo ss -tlnp | grep 3306` (WSL) et `netstat -ano | findstr :3306` (PowerShell) pour identifier le coupable |
| Base vide (0 tables) | Volume supprimé (`down -v`) | Relancer le générateur (`main.ts` avec `host: 'mysql'` dans un conteneur node sur le réseau `rendu-generator_default`) |

## Arrêt propre (optionnel)

Avant d'éteindre le PC, rien d'obligatoire — Docker s'arrête proprement tout seul. Pour arrêter manuellement le conteneur :

```bash
docker compose stop
```

(Ne pas utiliser `down -v` : le `-v` supprime le volume et donc toutes les données.)
