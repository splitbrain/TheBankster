CREATE TABLE categories (
    cat INTEGER NOT NULL PRIMARY KEY,
    top TEXT NOT NULL DEFAULT '',
    label TEXT NOT NULL DEFAULT ''
);

CREATE TABLE transactions (
  txid TEXT NOT NULL,
  account TEXT NOT NULL,
  datetime INTEGER NOT NULL,
  amount REAL NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  x_name TEXT NOT NULL DEFAULT '',
  x_bank TEXT NOT NULL DEFAULT '',
  x_acct TEXT NOT NULL DEFAULT '',
  cat INTEGER NULL,

  FOREIGN KEY (cat) REFERENCES categories(cat),
  PRIMARY KEY(txid, account)
);

CREATE TABLE rules (
  rule INTEGER NOT NULL PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  account TEXT NOT NULL DEFAULT '',
  debit INTEGER NOT NULL DEFAULT 0,
  description TEXT NOT NULL DEFAULT '',
  x_name TEXT NOT NULL DEFAULT '',
  x_bank TEXT NOT NULL DEFAULT '',
  x_acct TEXT NOT NULL DEFAULT ''
);