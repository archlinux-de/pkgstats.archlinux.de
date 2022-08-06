describe('Fun', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages\/[\w-]+$/ }).as('api-packages')
    cy.visit('/fun')
    cy.wait('@api-packages')
  })

  it('shows title', () => {
    cy.contains('h1', 'Fun statistics')
  })

  it('shows categories', () => {
    cy.contains('a', 'Browsers')
  })

  it('shows packages', () => {
    cy.get('[role=progressbar]').should('be.visible')
    cy.contains('a', 'firefox')
  })
})
