module.exports = {
  "plugins": {
    'rucksack-css': {
      "fallbacks": ( process.env.NODE_ENV === 'production' ),
      "autoprefixer": ( process.env.NODE_ENV === 'production' ),
    },
  },
};
