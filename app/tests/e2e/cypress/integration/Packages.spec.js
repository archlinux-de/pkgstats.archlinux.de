describe('Packages', () => {
  beforeEach(() => {
    cy.visit('/packages')
  })

  it('shows title', () => {
    cy.contains('h1', 'Package statistics')
  })

  it('searches', () => {
    cy.get('input[type=search]').type('foo')
    cy.contains('[role=alert]', 'packages found')
  })

  it('handles invalid input', () => {
    cy.get('input[type=search]').type('{bar}', { parseSpecialCharSequences: false })
    cy.contains('[role=alert]', 'Bad Request')
  })
})
