from filecmp import cmp

if cmp('etc/network/interfaces', 'etc/network/interfaces_bck'):
    print("True")
