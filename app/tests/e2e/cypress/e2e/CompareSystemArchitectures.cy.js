describe('Compare System Architectures', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/system-architectures/ }).as('api-compare-system-architectures')
    cy.visit('/compare/system-architectures/current')
    cy.wait('@api-compare-system-architectures')
  })

  it('shows title', () => {
    cy.contains('h1', 'Compare System Architectures')
  })

  it('shows chart', () => {
    cy.assertCanvasIsNotEmpty('[data-test=system-architecture-chart][data-test-rendered=true][style]')
  })
})
