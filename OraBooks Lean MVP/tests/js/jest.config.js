const path = require('path');

module.exports = {
  rootDir: path.resolve(__dirname, '../../..'),
  testEnvironment: 'jsdom',
  setupFiles: ['<rootDir>/jest.setup.js'],
  testMatch: ['<rootDir>/OraBooks Lean MVP/tests/js/*.test.js'],
  moduleDirectories: ['node_modules', '<rootDir>/OraBooks Lean MVP/tests/js/node_modules'],
  verbose: true
};
