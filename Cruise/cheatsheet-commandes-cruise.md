# Projet Cruise — Récap commandes & IPs

## IPs des VMs

| VM | Rôle | IP | Utilisateur SSH |
|----|------|-----|------------------|
| VM1 | master (control-plane) | `172.16.248.90` | `sampai_c` |
| VM2 | worker A | `172.16.248.184` | `soumar_b` |
| VM3 | worker B | `172.16.248.220` | `soumar_b` |

```bash
ssh sampai_c@172.16.248.90
ssh soumar_b@172.16.248.184
ssh soumar_b@172.16.248.220
```

`kubectl` n'est configuré que sur **VM1** — toutes les commandes `kubectl` se tapent depuis là, même pour agir sur des pods qui tournent physiquement sur VM2/VM3.

---

## Accès web (depuis le navigateur)

| Service | URL | Identifiants |
|---------|-----|---------------|
| **Application (front)** | http://172.16.248.90:30080 | — |
| **Grafana** | http://172.16.248.90:30300 | `admin` / `Mdpgrafanamdr1!` |
| **Registry privée** (API, pas une UI) | https://172.16.248.90:30500/v2/ | `backs` / `Jesuisnoir1!` |

---

## Vérifier l'état général du cluster

```bash
kubectl get nodes -o wide
kubectl get pods -A
```

```bash
kubectl get pods -n app -o wide          # front/back/db
kubectl get pods -n registry             # registry privée
kubectl get pods -n storage              # provisioner NFS
kubectl get pods -n monitoring -o wide   # Prometheus/Grafana/node-exporter
kubectl get svc -A                       # tous les Services + ports
```

---

## Application (frontend / backend / DB)

```bash
# Logs
kubectl logs -n app deployment/frontend
kubectl logs -n app deployment/backend
kubectl logs -n app db-0
kubectl logs -n app db-1

# Redémarrer un composant (relit la config/l'image)
kubectl rollout restart deployment/frontend -n app
kubectl rollout restart deployment/backend -n app

# Shell dans un pod
kubectl exec -n app -it deploy/backend -- sh
kubectl exec -n app -it db-0 -- sh
```

### Base de données (primaire `db-0` / réplica `db-1`)

```bash
# Mot de passe Postgres : Postgremdr1!
set +H   # desactive l'historique bash a cause du '!' dans le mdp

# Verifier la replication cote primaire
kubectl exec -n app -it db-0 -- env PGPASSWORD='Postgremdr1!' psql -U postgres -c "SELECT client_addr, state, sync_state FROM pg_stat_replication;"

# Verifier que la replica est bien en lecture seule
kubectl exec -n app -it db-1 -- env PGPASSWORD='Postgremdr1!' psql -U postgres -c "SELECT pg_is_in_recovery();"

# Requete directe sur la base applicative
kubectl exec -n app -it db-0 -- env PGPASSWORD='Postgremdr1!' psql -U app -d cruise -c "SELECT * FROM tasks;"
```

### Démo de résilience (tuer un pod, voir qu'il redémarre proprement)

```bash
kubectl delete pod db-0 -n app
kubectl get pods -n app -w          # observer le redemarrage, Ctrl+C pour sortir
```

---

## Registry privée

```bash
# Depuis WSL2 : pousser une image
docker tag <image>:latest 172.16.248.90:30500/<nom>:latest
docker login 172.16.248.90:30500 -u backs -p 'Jesuisnoir1!'
docker push 172.16.248.90:30500/<nom>:latest

# Depuis une VM : verifier qu'une image est bien accessible
sudo crictl pull --creds backs:'Jesuisnoir1!' 172.16.248.90:30500/<nom>:latest
```

---

## Monitoring (Prometheus / Grafana)

```bash
# Verifier que Prometheus scrape bien les 3 node-exporter
kubectl exec -n monitoring deployment/prometheus -- wget -qO- 'http://localhost:9090/api/v1/query?query=up'

# Sante de Grafana
kubectl exec -n monitoring deployment/grafana -- wget -qO- http://localhost:3000/api/health
```

Dashboard Grafana : **"Cruise - Monitoring cluster"**, 3 panels (CPU / RAM / espace disque par node), source de données Prometheus préconfigurée automatiquement.

---

## Stockage NFS

```bash
# Sur VM1 : voir les sous-dossiers crees par le provisioner (un par PVC)
ls -la /srv/nfs/cruise/

# Lister les PVC du cluster
kubectl get pvc -A
```

---

## Rebuild complet d'une image (ex : la DB)

Depuis WSL2, dans `~/Cruise/group-1077779/cruise-app` :
```bash
docker build -t cruise-app-db:latest ./db
docker tag cruise-app-db:latest 172.16.248.90:30500/db:latest
docker push 172.16.248.90:30500/db:latest
```

Puis sur VM1, forcer le redéploiement :
```bash
kubectl rollout restart statefulset/db -n app
kubectl rollout restart deployment/backend -n app
kubectl rollout restart deployment/frontend -n app
```

---

## Ansible (provisioning automatisé du cluster)

Depuis WSL2, dans `~/Cruise/group-1077779/cruise-app/ansible` :
```bash
ansible-playbook site.yml
```
(mots de passe `sudo` déjà dans `inventory.ini` via `ansible_become_pass` — fichier **non committé**, dans `.gitignore`)

---

## Applique/modifie un manifeste Kubernetes

```bash
kubectl apply -f <fichier>.yaml
```

Fichiers concernés (sur VM1, home de `sampai_c`) : `k8s-backend.yaml`, `k8s-frontend.yaml`, `k8s-db.yaml`, `k8s-nfs-provisioner.yaml`, `k8s-monitoring.yaml`, `registry.yaml`.
