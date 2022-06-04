describe('Fun', () => {
  beforeEach(() => {
    cy.visit('/fun')
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
