CREATE TABLE transactions (
  txid TEXT NOT NULL,
  account TEXT NOT NULL,
  datetime INT NOT NULL,
  amount REAL NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  x_name TEXT NOT NULL DEFAULT '',
  x_bank TEXT NOT NULL DEFAULT '',
  x_acct TEXT NOT NULL DEFAULT '',

  PRIMARY KEY(txid, account)
);