# Projet Cruise (ISR-ORC4) — Résumé complet

Cluster Kubernetes implémenté de A à Z sur 3 VMs ETNACloud (Debian 12, 2 vCPU / 4 Go RAM / 50 Go chacune), en solo.

- **VM1** — `172.16.248.90` — control-plane (master)
- **VM2** — `172.16.248.184` — worker A
- **VM3** — `172.16.248.220` — worker B

---

## 1. Architecture générale

**Namespaces** : `kube-system` (K8s natif), `registry` (registry privée), `storage` (provisioner NFS), `monitoring` (Prometheus/Grafana), `app` (frontend/backend/DB), plus les namespaces internes de Calico (`calico-system`, `calico-apiserver`, `tigera-operator`).

**Réseau** : Calico CNI **v3.30.7** en encapsulation **VXLAN** (forcée, voir incident majeur ci-dessous), `NetworkPolicy` disponibles pour cloisonner les namespaces si besoin.

**Stockage** : serveur NFS sur VM1 (`/srv/nfs/cruise`), provisioning dynamique via `nfs-subdir-external-provisioner`, `StorageClass` `nfs-client`.

**Stack applicative** : Node.js/Express (front + back) et PostgreSQL 16, toutes les images construites depuis `alpine:3.20` uniquement — aucune image officielle `node:*` ou `postgres:*` du Docker Hub, conformément à la contrainte du sujet. Seules exceptions autorisées et utilisées : `alpine`, `registry:2`, et les images de monitoring (`prom/*`, `grafana/grafana`, explicitement exemptées par le sujet).

---

## 2. Étape par étape

### Étape I — Schéma d'architecture
Rôles des 3 VMs, réseau (Calico), stockage (NFS), namespaces et objets K8s attendus définis en amont.

### Étape II — Images Docker (front / back / DB)
Application de démo : liste de tâches (CRUD), en 3 images :
- **Frontend** (Express) : sert la page statique et **proxy** `/api/*` vers le backend côté serveur — le navigateur ne parle jamais directement au backend, dont le nom DNS interne (`backend-svc`) n'est résolvable que depuis l'intérieur du cluster.
- **Backend** (Express + `pg`) : API REST des tâches.
- **DB** (Postgres 16 via `apk`) : `entrypoint.sh` réécrit à la main pour reproduire ce que fait l'image officielle (détection premier démarrage, création user/db, exécution des scripts d'init).

### Étape III — Registry privée
`registry:2` déployé dans le cluster (namespace `registry`), TLS auto-signé (SAN sur l'IP de VM1), authentification `htpasswd` (bcrypt), exposé en `NodePort` (30500). Confiance du certificat installée comme CA système sur les 3 nodes (`update-ca-certificates`) ; côté poste de dev (Docker Desktop/WSL2), contournement pragmatique via `insecure-registries` (daemon Docker Desktop difficile à faire confiance à une CA custom).

### Étape IV — Cluster Kubernetes
`kubeadm` (v1.33.13) + `containerd`, 1 master + 2 workers. Étape la plus riche en incidents (voir section dédiée).

### Étapes V/VI/VII — Pods, Deployments, Service Discovery
- `Deployment` frontend et backend (2 replicas chacun), `podAntiAffinity` (preferred) pour répartir les replicas entre les 2 workers — condition de la démo de résilience.
- `Service` `backend-svc` (ClusterIP) et `frontend-svc` (NodePort 30080).
- Configuration injectée via un `ConfigMap` (`app-config`) et des `Secret` (`db-credentials`, `registry-credentials`), avec mapping explicite des noms de variables entre le code applicatif et l'image DB.

### Étape VIII — Stockage persistant
Serveur NFS sur VM1 (le node sans charge applicative, le plus stable), client NFS sur les 3 VMs, provisioning dynamique K8s. Migration du `StatefulSet` DB de `emptyDir` vers un vrai `PersistentVolumeClaim`. Démo de résilience validée : tâche créée, pod DB supprimé de force, redémarrage complet, donnée toujours présente.

### Étape IX — Cluster BDD (réplication)
`StatefulSet` à 2 replicas : `db-0` (primaire) et `db-1` (réplica), rôle détecté automatiquement via le nom du pod dans `entrypoint.sh`. Réplication en streaming (`pg_basebackup -R` + fichier `.pgpass` pour l'authentification continue). Le backend pointe explicitement sur `db-0.db-svc` (pas `db-svc`) pour ne jamais écrire sur la réplique en lecture seule.

### Étape X — Monitoring
`node-exporter` en `DaemonSet` (hostNetwork, un pod par VM y compris le master), `Prometheus` (cible les 3 IP de VMs en `static_configs`, plus simple que la découverte de service K8s), `Grafana` (NodePort 30300, datasource Prometheus provisionnée automatiquement, dashboard "Cruise - Monitoring cluster" construit à la main avec 3 panels : CPU / RAM / espace disque par node). Persistance ajoutée via PVC NFS après avoir perdu un premier dashboard.

### Bonus — Playbook Ansible
`site.yml` (3 plays : prérequis communs, init master, join workers) automatisant l'intégralité du bootstrap du cluster, avec la correction VXLAN directement intégrée dans la configuration Calico déployée — une réinstallation via ce playbook n'aurait jamais l'incident réseau ci-dessous. Testé idempotent contre le cluster déjà en production (`failed=0`, `unreachable=0`).

---

## 3. Incidents majeurs et résolutions

| # | Incident | Cause | Résolution |
|---|----------|-------|------------|
| 1 | Calico v3.31 : `calico-typha`/`calico-node` crashent (`CPU does not support x86-64-v2`) | Images Calico récentes compilées avec `GOAMD64=v2`, CPU virtuel ETNACloud générique sans ces instructions | Downgrade vers Calico **v3.30.7** |
| 2 | Suppression de l'install Calico v3.31 bloquée en `Terminating` | Finalizers jamais levés (opérateur supprimé avant d'avoir fini son nettoyage) | `kubeadm reset` complet plutôt que débloquer les finalizers à la main |
| 3 | VM1 reste `NotReady` après le reset (`cni plugin not initialized`) | `rm -rf /etc/cni/net.d` fait à chaud a cassé la surveillance inotify de containerd sur ce dossier | `systemctl restart containerd` |
| 4 | Frontend ↔ backend timeout **uniquement entre VMs différentes** | `VXLANCrossSubnet` n'encapsule pas le trafic entre nodes du même sous-réseau ; ETNACloud rejette (anti-spoofing) les paquets à IP de pod brute | Forcer l'encapsulation **VXLAN** systématique (patch de l'`Installation` + recréation de l'`IPPool`) |
| 5 | Suite à l'incident 4 : `ServiceUnavailable` en tentant de supprimer l'`IPPool` | API `v3.projectcalico.org` temporairement indisponible | Suppression via le chemin CRD alternatif (`ippools.crd.projectcalico.org`) |
| 6 | Suite à l'incident 4 : `calico-node` sur VM2/VM3 bloqués (`local VTEP not yet known`) | Pods actifs depuis avant le changement, jamais resynchronisés | Redémarrage forcé des pods `calico-node` concernés |
| 7 | Image DB : `could not create lock file "/run/postgresql/..."` | Dossier du socket Unix Postgres jamais créé dans l'image `alpine` | Ajout du `mkdir`/`chown` correspondant dans le `Dockerfile` |
| 8 | Image DB : init partielle piégée dans le volume Docker local | `initdb` avait déjà écrit `PG_VERSION` avant l'échec précédent ; l'entrypoint a cru la base déjà prête | `docker compose down -v` pour repartir d'un volume propre |
| 9 | Image DB : `no password supplied` en local | `initdb --auth=scram-sha-256` exige un mot de passe même pour les connexions locales | Export de `PGPASSWORD` dans l'entrypoint |
| 10 | `StatefulSet` DB : impossible de passer de `emptyDir` à un PVC via `kubectl apply` | `volumeClaimTemplates` est un champ immuable | Suppression puis recréation complète du `StatefulSet` |
| 11 | `db-0`/`db-1` : `initdb: could not change permissions` sur le volume NFS | Un volume monté au runtime masque le `chown` fait au moment du build de l'image | `initContainer` dédié tournant en `root` pour corriger les permissions |
| 12 | Réplica `db-1` : `data directory has invalid permissions` après `pg_basebackup` | Contrairement à `initdb`, `pg_basebackup` ne force pas les permissions `0700` du dossier cible | `chmod 700 "$PGDATA"` ajouté avant le démarrage, quel que soit le chemin d'init |
| 13 | Grafana : `Connection refused` en continu malgré `1/1 Running` | Tentatives infinies de contacter `grafana.com` pour la liste des plugins, cluster sans accès Internet sortant | `GF_INSTALL_PLUGINS=""` + `GF_PLUGINS_PREINSTALL_DISABLED=true` |
| 14 | Grafana : `OOMKilled` juste après le fix précédent | Limite mémoire (256Mi) trop juste au démarrage | Limite augmentée à 512Mi |
| 15 | Grafana : dashboard importé disparu | Aucun volume persistant, base SQLite interne perdue à chaque redémarrage du pod | `PersistentVolumeClaim` (NFS) monté sur `/var/lib/grafana` |

**Fil conducteur** : la majorité des incidents (4, 5, 6 notamment) viennent d'une incompatibilité entre le comportement par défaut de Calico et les protections réseau spécifiques à la plateforme d'hébergement ETNACloud — un point fort à mettre en avant à l'oral, ce n'est pas une erreur de configuration mais une découverte d'infrastructure via un diagnostic méthodique (élimination couche par couche : DNS, réseau intra-node, réseau inter-node, BGP, configuration IPAM).

---

## 4. Démonstrations possibles pour la soutenance

- **Résilience applicative** : tuer un pod frontend ou backend, montrer que l'autre replica (sur l'autre worker) continue de servir.
- **Persistance des données** : supprimer le pod `db-0`, montrer que les tâches créées avant sont toujours là après son redémarrage complet.
- **Réplication** : `pg_stat_replication` sur le primaire, `pg_is_in_recovery()` sur la réplique.
- **Monitoring en direct** : dashboard Grafana pendant une charge ou un incident simulé.
- **Reproductibilité** : lancer le playbook Ansible sur le cluster en marche, montrer `failed=0` (idempotence).
- **Sécurité** : TLS + auth sur la registry, cloisonnement par namespace, `Secret` jamais en clair dans les manifestes versionnés.
