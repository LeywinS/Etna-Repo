# MesTâches — site PHP / MySQL

Petite application web complète : inscription, connexion, et gestion de tâches personnelles (ajouter, modifier, cocher, supprimer, filtrer).

**Stack :** PHP 8.3 (Apache) · MySQL 8.4 · PDO · Docker Compose · phpMyAdmin

## Lancer le projet

```bash
docker compose up -d --build
```

| Service     | URL                    |
|-------------|------------------------|
| Site        | http://localhost:8080  |
| phpMyAdmin  | http://localhost:8081 (root / rootpass) |

La base et les tables sont créées automatiquement au premier démarrage par `sql/init.sql`.

Arrêter : `docker compose down` — tout supprimer, base comprise : `docker compose down -v`

## Arborescence

```
site-php/
├── Dockerfile              # PHP 8.3 + Apache, DocumentRoot = public/
├── docker-compose.yml      # web + db + phpmyadmin
├── sql/
│   └── init.sql            # schéma : users, tasks
├── src/                    # code NON exposé en HTTP
│   ├── db.php              # connexion PDO
│   ├── bootstrap.php       # session, CSRF, auth, helpers
│   └── layout.php          # header / footer HTML
└── public/                 # seul dossier servi par Apache
    ├── index.php           # liste + ajout + filtres + progression
    ├── register.php        # inscription
    ├── login.php           # connexion
    ├── logout.php          # déconnexion (POST)
    ├── task_edit.php       # formulaire d'édition
    ├── task_action.php     # create / update / toggle / delete
    └── assets/style.css    # thème clair / sombre auto
```

## Sécurité intégrée

| Risque | Protection |
|--------|-----------|
| Injection SQL | Requêtes préparées PDO, `ATTR_EMULATE_PREPARES = false` |
| XSS | Toute sortie passe par `e()` (`htmlspecialchars`) |
| CSRF | Jeton aléatoire sur tous les formulaires POST, vérifié par `hash_equals` |
| Mots de passe | `password_hash` / `password_verify` (bcrypt), rehash automatique |
| Fixation de session | `session_regenerate_id(true)` à la connexion |
| Cookies | `HttpOnly`, `SameSite=Lax`, `Secure` si HTTPS |
| IDOR | Chaque requête sur une tâche filtre `AND user_id = ?` |
| Énumération de comptes | Message de connexion générique |
| Exposition du code | Seul `public/` est servi ; `expose_php = Off` |

## Pour aller en production

- Changer tous les mots de passe de `docker-compose.yml` (idéalement via un fichier `.env`).
- Retirer phpMyAdmin ou ne pas exposer son port.
- Mettre un reverse proxy HTTPS devant (Traefik, Caddy, Nginx).
- Ajouter une limitation des tentatives de connexion (rate limiting).
