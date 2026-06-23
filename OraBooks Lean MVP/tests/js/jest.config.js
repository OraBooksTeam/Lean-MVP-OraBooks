module.exports = {
  testEnvironment: 'jsdom',
  setupFiles: [require.resolve('./jest.setup.js')],
  testMatch: ['**/*.test.js'],
  moduleDirectories: ['node_modules'],
  verbose: true
};
