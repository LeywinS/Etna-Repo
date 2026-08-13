# Ansible BIND9 — DNS autoritaire multi-views

Deux rôles, entièrement pilotés par variables (aucune donnée en dur) :

- `bind9_setup` : installe BIND9, crée l'arborescence, génère `/etc/bind/named.conf*`, valide la conf complète, démarre le service.
- `bind9_update_zones` : génère les fichiers de zone sous `/var/cache/bind/primary/<view>/<zone>`, validés par `named-checkzone` avant mise en place.

## Modèle de données (group_vars)

```yaml
bind9_views:            # L'ORDRE COMPTE : première view qui matche gagne.
  - name: admin         # -> mettre "any" en dernier.
    match_clients: ["10.30.0.0/16"]
  - name: external
    match_clients: ["any"]

bind9_zones:
  - name: example.com
    ttl: 86400
    soa: {primary: ns1.example.com., admin: hostmaster.example.com.,
          serial: 2026081301, refresh: 3600, retry: 600,
          expire: 604800, minimum: 300}
    views:              # une zone peut n'exister que dans certaines views
      admin:
        records:
          - ["@",   "NS", "ns1.example.com."]
          - ["ns1", "A",  "10.30.0.53"]
```

Règles à respecter :
- **Incrémenter le serial** (AAAAMMJJnn) à chaque modification d'une zone.
- Tout NS déclaré doit avoir un enregistrement A/AAAA (sinon `named-checkzone` refuse — c'est voulu).
- Noms absolus terminés par un point, sinon `$ORIGIN` est concaténé.

## Utilisation

```bash
ansible-playbook --syntax-check site.yml   # 1. syntaxe
ansible-playbook site.yml --check --diff   # 2. dry-run : montre ce qui changerait
ansible-playbook site.yml                  # 3. DNS from scratch complet
ansible-playbook site.yml --tags bind9_zones   # zones uniquement (DNS existant)
```

## Vérifier que ça fonctionne

Sur le serveur : `named-checkconf /etc/bind/named.conf` (silence = OK),
`systemctl status bind9`, `rndc status`.

Test des views depuis des clients de réseaux différents (ou avec
`dig -b <ip_source>` si vous disposez d'IP dans les bons réseaux) :

```bash
dig @<ip_serveur> www.example.com A +short
```

La même question doit retourner la réponse propre à la view du client.
Une zone non déclarée dans la view du client répond REFUSED ; un
enregistrement absent de sa view répond NXDOMAIN.

Filets de sécurité intégrés : toute conf ou zone invalide est rejetée
AVANT mise en place (`validate:` / task de validation) — le service
continue de tourner avec l'ancienne configuration.
