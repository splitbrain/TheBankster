CREATE TABLE "category" (
    id INTEGER NOT NULL PRIMARY KEY,
    top TEXT NOT NULL DEFAULT '',
    label TEXT NOT NULL DEFAULT ''
);

CREATE TABLE "transaction" (
  "id" TEXT NOT NULL PRIMARY KEY,
  "account" TEXT NOT NULL,
  "datetime" INTEGER NOT NULL,
  "amount" REAL NOT NULL,
  "description" TEXT NOT NULL DEFAULT '',
  "x_name" TEXT NOT NULL DEFAULT '',
  "x_bank" TEXT NOT NULL DEFAULT '',
  "x_acct" TEXT NOT NULL DEFAULT '',
  "category_id" INTEGER NULL,

  FOREIGN KEY ("category_id") REFERENCES "category"("id")
);

CREATE TABLE "rule" (
  "id" INTEGER NOT NULL PRIMARY KEY,
  "category_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL DEFAULT '',
  "account" TEXT NOT NULL DEFAULT '',
  "debit" INTEGER NOT NULL DEFAULT 0,
  "description" TEXT NOT NULL DEFAULT '',
  "x_name" TEXT NOT NULL DEFAULT '',
  "x_bank" TEXT NOT NULL DEFAULT '',
  "x_acct" TEXT NOT NULL DEFAULT '',

  FOREIGN KEY ("category_id") REFERENCES "category"("id")
);