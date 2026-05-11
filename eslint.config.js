// @ts-check

import { defineConfig } from "eslint/config";
import eslint from "@eslint/js";
import tseslint from "typescript-eslint";
import { includeIgnoreFile } from "@eslint/config-helpers";
import { fileURLToPath } from "node:url";

const gitignorePath = fileURLToPath(new URL(".gitignore", import.meta.url));

export default defineConfig([
  includeIgnoreFile(gitignorePath, "Imported .gitignore patterns"),
  {
  ignores: [],
  extends: [
    eslint.configs.recommended,
    tseslint.configs.recommendedTypeChecked,
    tseslint.configs.strict,
    tseslint.configs.stylistic,
  ],
  languageOptions: {
    parserOptions: { projectService: true },
  },
  rules: {
    "no-unused-vars": "off",
    "@typescript-eslint/no-unused-vars": [
      "error",
      {
        argsIgnorePattern: "^_",
        caughtErrorsIgnorePattern: "^_",
        destructuredArrayIgnorePattern: "^_",
        varsIgnorePattern: "^_",
        ignoreRestSiblings: true,
      },
    ],
    "@typescript-eslint/explicit-member-accessibility": [
      "error",
      {
        overrides: {
          constructors: "no-public",
        },
      },
    ],
    "@typescript-eslint/prefer-readonly": "error",
    "no-unused-expressions": "off",
    "@typescript-eslint/no-unused-expressions": "error",
  },
}]);
