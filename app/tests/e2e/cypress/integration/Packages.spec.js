describe('Packages', () => {
  beforeEach(() => {
    cy.visit('/packages')
  })

  it('shows title', () => {
    cy.contains('h1', 'Package statistics')
  })

  it('searches', () => {
    cy.get('input[type=search]').type('firefox')
    cy.get('[role=progressbar]').should('be.visible')
    cy.contains('[role=alert]', 'packages found')
    cy.contains('a', 'firefox')
  })

  it('handles invalid input', () => {
    cy.get('input[type=search]').type('{bar}', { parseSpecialCharSequences: false })
    cy.contains('[role=alert]', 'Bad Request')
  })
})
