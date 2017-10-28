CREATE TABLE "category" (
  id    INTEGER NOT NULL PRIMARY KEY,
  top   TEXT    NOT NULL DEFAULT '',
  label TEXT    NOT NULL DEFAULT '',

  UNIQUE ("top", "label")
);

CREATE TABLE "transaction" (
  "id"          INTEGER NOT NULL PRIMARY KEY,
  "account"     TEXT    NOT NULL,
  "ts"          INTEGER NOT NULL,
  "amount"      REAL    NOT NULL,
  "description" TEXT    NOT NULL DEFAULT '',
  "x_name"      TEXT    NOT NULL DEFAULT '',
  "x_bank"      TEXT    NOT NULL DEFAULT '',
  "x_acct"      TEXT    NOT NULL DEFAULT '',
  "category_id" INTEGER NULL,

  UNIQUE ("ts", "description", "amount", "x_bank", "x_name", "x_acct"),
  FOREIGN KEY ("category_id") REFERENCES "category" ("id") ON DELETE SET NULL 
);

CREATE TABLE "rule" (
  "id"          INTEGER NOT NULL PRIMARY KEY,
  "enabled"     BOOLEAN NOT NULL DEFAULT 0,
  "category_id" INTEGER NOT NULL,
  "account"     TEXT    NOT NULL DEFAULT '',
  "debit"       INTEGER NOT NULL DEFAULT 0,
  "description" TEXT    NOT NULL DEFAULT '',
  "x_name"      TEXT    NOT NULL DEFAULT '',
  "x_bank"      TEXT    NOT NULL DEFAULT '',
  "x_acct"      TEXT    NOT NULL DEFAULT '',

  FOREIGN KEY ("category_id") REFERENCES "category" ("id") ON DELETE CASCADE
);