describe('Compare', () => {
  beforeEach(() => {
    cy.visit('/compare/packages#packages=chromium,firefox')
  })

  it('shows title', () => {
    cy.contains('h1', 'Compare Packages')
  })

  it('shows chart', () => {
    cy.assertCanvasIsNotEmpty('#package-chart[style]')
  })
})
