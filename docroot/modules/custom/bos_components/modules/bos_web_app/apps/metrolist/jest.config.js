module.exports = {
  "moduleNameMapper": {
    "\\.(css|s[ca]ss|less|styl)$": "<rootDir>/__mocks__/styleMock.js",
    "\\.(svg|webp|png|jpe?g|gif)$": "<rootDir>/__mocks__/fileMock.js",
    "^@patterns/(.*)": "<rootDir>/patterns/$1",
    "^@util/(.*)": "<rootDir>/src/util/$1",
    "^@globals/(.*)$": "<rootDir>/src/globals/$1",
    "^@components/(.*)$": "<rootDir>/src/components/$1",
    "^__mocks__/(.*)$": "<rootDir>/__mocks__/$1",
  },
};
