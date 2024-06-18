module.exports = {
  "presets": [
    [
      "@babel/preset-env", {
        "useBuiltIns": "entry",
        "corejs": 3,
      },
    ],
    "@babel/preset-react",
  ],
  "plugins": [
    // "@babel/plugin-proposal-optional-chaining",
    // "@babel/plugin-proposal-object-rest-spread",
    // "@babel/plugin-transform-destructuring",
    // "@babel/plugin-transform-spread",
    // "@babel/plugin-transform-arrow-functions",
    // "@babel/plugin-transform-template-literals",
    // "@babel/plugin-transform-parameters",
    // "@babel/plugin-transform-block-scoping",
    // "@babel/plugin-transform-classes",
    // "@babel/plugin-transform-shorthand-properties",
  ],
};
